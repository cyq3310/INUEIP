<script setup lang="ts">
import { nextTick, ref } from 'vue'
import { portalApi } from '../api'
import { useUserStore } from '../stores/user'
import type { AnnouncementItem } from '../types'

const store = useUserStore()

const visible = ref(false)
const loading = ref(false)
const detail = ref<AnnouncementItem | null>(null)

/** 弹窗内容容器与水印图层 */
const contentRef = ref<HTMLElement>()
const watermarkRef = ref<HTMLElement>()
/** Canvas 生成的水印瓦片底图 */
const watermarkUrl = ref('')
let observer: MutationObserver | null = null

const formatDate = (value: string | null) => (value ? value.slice(0, 10) : '')

const pad = (n: number) => String(n).padStart(2, '0')

/** 当前时间：YYYY-MM-DD HH:mm */
const nowText = () => {
  const d = new Date()
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
}

/** 绘制倾斜文字瓦片，转 DataURL 用于 CSS 平铺（2 倍画布保证高清屏不模糊） */
const buildWatermark = (text: string) => {
  const width = 260
  const height = 140
  const scale = 2
  const canvas = document.createElement('canvas')
  canvas.width = width * scale
  canvas.height = height * scale
  const ctx = canvas.getContext('2d')
  if (!ctx) {
    return
  }
  ctx.scale(scale, scale)
  ctx.font = '14px "PingFang SC", "Microsoft YaHei", sans-serif'
  ctx.fillStyle = 'rgba(31, 45, 61, 0.10)'
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  ctx.translate(width / 2, height / 2)
  ctx.rotate((-22 * Math.PI) / 180)
  ctx.fillText(text, 0, 0)
  watermarkUrl.value = canvas.toDataURL('image/png')
}

/** 身份标识：昵称 + 账号（重复时去重） */
const identityText = () => {
  const parts = [store.profile?.nickname, store.profile?.username].filter(Boolean) as string[]
  return Array.from(new Set(parts)).join(' ')
}

/** 水印图层被删除或样式被篡改时恢复，保证截图必然含水印 */
const enforceWatermark = () => {
  const parent = contentRef.value
  const el = watermarkRef.value
  if (!parent || !el || !watermarkUrl.value) {
    return
  }
  if (!parent.contains(el)) {
    parent.appendChild(el)
  }
  const background = `url("${watermarkUrl.value}")`
  if (el.style.backgroundImage !== background) {
    el.style.backgroundImage = background
  }
  if (el.style.backgroundRepeat !== 'repeat') {
    el.style.backgroundRepeat = 'repeat'
  }
  if (!el.classList.contains('pointer-events-none')) {
    el.classList.add('pointer-events-none')
  }
}

const startObserver = async () => {
  stopObserver()
  await nextTick()
  const parent = contentRef.value
  const el = watermarkRef.value
  if (!parent || !el) {
    return
  }
  observer = new MutationObserver(enforceWatermark)
  observer.observe(parent, { childList: true, subtree: true })
  observer.observe(el, { attributes: true, attributeFilter: ['style', 'class'] })
}

function stopObserver() {
  observer?.disconnect()
  observer = null
}

const onClosed = () => {
  stopObserver()
  watermarkUrl.value = ''
}

/** 打开指定公告的详情（外部通过 ref 调用） */
const open = (item: AnnouncementItem) => {
  visible.value = true
  loading.value = true
  detail.value = item

  const text = [identityText(), nowText()].filter(Boolean).join(' ')
  buildWatermark(text)

  portalApi
    .announcementDetail(item.id)
    .then((res) => (detail.value = res.data))
    .catch(() => {
      visible.value = false
    })
    .finally(() => (loading.value = false))
}

defineExpose({ open })
</script>

<template>
  <el-dialog v-model="visible" width="min(1125px, 92vw)" destroy-on-close top="6vh" @opened="startObserver" @closed="onClosed">
    <div ref="contentRef" v-loading="loading" class="relative min-h-[120px]">
      <!-- 用户名水印：常显平铺，不拦截鼠标事件 -->
      <div
        v-if="watermarkUrl"
        ref="watermarkRef"
        class="pointer-events-none absolute inset-0 z-10 bg-repeat"
        :style="{ backgroundImage: `url(${watermarkUrl})` }"
      ></div>

      <div class="flex items-center gap-2">
        <span
          v-if="detail?.is_top === 1"
          class="rounded bg-[#FFF1F0] px-1.5 py-0.5 text-[12px] font-semibold text-[#F5222D]"
        >
          置顶
        </span>
        <h3 class="text-[20px] font-semibold text-ink">{{ detail?.title }}</h3>
      </div>
      <p class="mt-1.5 text-[13px] text-ink-mute">发布于 {{ formatDate(detail?.published_at ?? null) }}</p>
      <div
        class="ann-detail-content mt-5 border-t border-[#F0F3FA] pt-5 text-[15px] leading-7 text-ink"
        v-html="detail?.content || ''"
      ></div>
    </div>
    <template #footer>
      <el-button @click="visible = false">关闭</el-button>
    </template>
  </el-dialog>
</template>

<style scoped>
.ann-detail-content :deep(img) {
  max-width: 100%;
  height: auto;
}
.ann-detail-content :deep(table) {
  width: 100%;
  border-collapse: collapse;
}
.ann-detail-content :deep(td),
.ann-detail-content :deep(th) {
  border: 1px solid #e3e9f5;
  padding: 6px 10px;
}
</style>
