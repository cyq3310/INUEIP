<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { Image as ImageIcon, Trash2, Upload } from 'lucide-vue-next'
import { portalThemeApi } from '../../api'
import { useUserStore } from '../../stores/user'
import type { PortalThemeItem } from '../../types'

const store = useUserStore()

/** 与后端一致的单图大小上限（字节），超出时前端直接拦截，不再发起上传请求 */
const MAX_FILE_SIZE = 5 * 1024 * 1024

const loading = ref(false)
const list = ref<PortalThemeItem[]>([])
const gallery = ref<Record<string, string[]>>({})
const fileInput = ref<HTMLInputElement | null>(null)
const pendingModule = ref('')

const fetchList = () => {
  loading.value = true
  portalThemeApi
    .list()
    .then((res) => {
      list.value = res.data.list
      return Promise.all(res.data.list.map((item) => fetchGallery(item.module)))
    })
    .catch(console.error)
    .finally(() => (loading.value = false))
}

/** 拉取某模块目录下当前仍存在的背景图（后端按文件系统过滤，已删除的不返回） */
const fetchGallery = (module: string): Promise<void> => {
  return portalThemeApi
    .images(module)
    .then((res) => {
      gallery.value[module] = res.data.list
    })
    .catch(console.error)
}

const selectImage = (item: PortalThemeItem, path: string) => {
  if (!store.hasPermission('portal-theme:update')) {
    return
  }
  item.image_path = path
  save(item)
}

const pickFile = (module: string) => {
  pendingModule.value = module
  fileInput.value?.click()
}

const handleFileChange = (event: Event) => {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  // 清空 value，允许再次选择同一个文件
  input.value = ''
  if (!file) {
    return
  }
  if (file.size > MAX_FILE_SIZE) {
    ElMessage.error(`图片大小 ${(file.size / 1024 / 1024).toFixed(2)}MB 已超过 5MB 上限，请压缩后再上传`)
    return
  }

  const item = list.value.find((row) => row.module === pendingModule.value)
  if (!item) {
    return
  }

  portalThemeApi
    .upload(item.module, file)
    .then((res) => {
      item.image_path = res.data.image_path
      return portalThemeApi.save({ module: item.module, image_path: item.image_path, opacity: item.opacity })
    })
    .then(() => {
      ElMessage.success('上传并保存成功')
      fetchGallery(item.module)
    })
    .catch(console.error)
}

const save = (item: PortalThemeItem) => {
  portalThemeApi
    .save({ module: item.module, image_path: item.image_path ?? '', opacity: item.opacity })
    .then(() => ElMessage.success('保存成功'))
    .catch(console.error)
}

const clearImage = (item: PortalThemeItem) => {
  item.image_path = null
  save(item)
}

/** 删除某张历史背景图（移入回收站），成功后从本地画廊移除 */
const removeImage = (module: string, path: string) => {
  if (!store.hasPermission('portal-theme:update')) {
    return
  }
  portalThemeApi
    .removeImage(module, path)
    .then(() => {
      gallery.value[module] = (gallery.value[module] ?? []).filter((p) => p !== path)
      // 若删掉的是当前选用图，清空配置
      const item = list.value.find((row) => row.module === module)
      if (item && item.image_path === path) {
        item.image_path = null
      }
      ElMessage.success('已删除并移入回收站')
    })
    .catch(console.error)
}

onMounted(fetchList)
</script>

<template>
  <div class="rounded-2xl bg-white p-6 shadow-card">
    <div class="mb-5">
      <h2 class="text-base font-medium text-ink">门户分区背景图</h2>
      <p class="mt-1 text-xs text-ink-mute">
        上传背景图并调整不透明度以适配门户风格；图片按分区分别存放于 uploads/portal 下的各自目录
      </p>
      <ul class="mt-2 space-y-1 text-xs text-ink-mute">
        <li>· 建议尺寸：1920×260 及以上宽幅横图（宽高比约 7.5:1）；门户实际展示为宽度铺满、高度约 250px</li>
        <li>· 支持格式：jpg / jpeg / png / webp / gif，单个图片大小不超过 5MB</li>
        <li>· 图片按区域裁剪居中显示，超出比例的部分会被裁掉，画面主体请尽量居中</li>
      </ul>
    </div>

    <div v-loading="loading" class="space-y-5">
      <div v-for="item in list" :key="item.module" class="rounded-2xl border border-[#EEF1F8] p-5">
        <div class="mb-4 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <ImageIcon :size="16" class="text-primary-deep" />
            <span class="text-sm font-medium text-ink">{{ item.name }}</span>
          </div>
          <div class="flex items-center gap-2">
            <el-button
              v-if="store.hasPermission('portal-theme:upload')"
              type="primary"
              plain
              @click="pickFile(item.module)"
            >
              <Upload :size="15" class="mr-1" />{{ item.image_path ? '更换图片' : '上传图片' }}
            </el-button>
            <el-button v-if="item.image_path && store.hasPermission('portal-theme:update')" @click="clearImage(item)">
              清除
            </el-button>
          </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_300px]">
          <!-- 效果预览：按门户实际展示比例（约 7.5:1）等比缩放 -->
          <div class="relative aspect-[15/2] min-h-[110px] overflow-hidden rounded-xl bg-[#F5F7FA]">
            <img
              v-if="item.image_path"
              :src="item.image_path"
              class="h-full w-full object-cover"
              :style="{ opacity: item.opacity / 100 }"
              alt="背景预览"
            />
            <div v-if="item.image_path" class="absolute inset-0 flex flex-col justify-center px-5">
              <span class="text-[15px] font-semibold text-ink">早上好，同事</span>
              <span class="mt-0.5 text-xs text-ink-sub">欢迎回来，准备好开始新的一天了吗？</span>
              <div class="mt-2 flex gap-2">
                <span class="h-5 w-20 rounded-md bg-white/85"></span>
                <span class="h-5 w-20 rounded-md bg-white/85"></span>
                <span class="h-5 w-20 rounded-md bg-white/85"></span>
              </div>
            </div>
            <div v-else class="absolute inset-0 flex items-center justify-center text-xs text-ink-mute">
              未配置背景图，门户将显示默认纯色背景
            </div>
          </div>

          <!-- 参数与保存 -->
          <div class="flex flex-col justify-center gap-5">
            <div>
              <div class="mb-2 flex items-center justify-between text-xs text-ink-sub">
                <span>不透明度</span>
                <span class="tabular text-ink">{{ item.opacity }}%</span>
              </div>
              <el-slider v-model="item.opacity" :min="0" :max="100" :disabled="!item.image_path" />
              <p class="mt-1 text-[11px] text-ink-mute">数值越低图片越淡，越融入页面背景</p>
            </div>
            <el-button
              v-if="store.hasPermission('portal-theme:update')"
              type="primary"
              :disabled="!item.image_path"
              @click="save(item)"
            >
              保存配置
            </el-button>
          </div>
        </div>

        <!-- 历史背景图：以文件系统为准，仅显示后端仍存在的图片，点击复用 -->
        <div v-if="gallery[item.module]?.length" class="border-t border-[#EEF1F8] pt-4">
          <div class="mb-2 text-xs text-ink-sub">曾上传的背景图（点击复用，已删除的不显示）</div>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="path in gallery[item.module]"
              :key="path"
              type="button"
              :disabled="!store.hasPermission('portal-theme:update')"
              class="group relative h-14 w-24 overflow-hidden rounded-lg border-2 transition-colors"
              :class="item.image_path === path ? 'border-primary' : 'border-transparent hover:border-[#C9D4EE] disabled:cursor-not-allowed'"
              @click="selectImage(item, path)"
            >
              <img :src="path" class="h-full w-full object-cover" alt="历史背景" />
              <span
                v-if="store.hasPermission('portal-theme:update')"
                class="absolute right-0 top-0 flex h-5 w-5 items-center justify-center bg-black/45 text-white opacity-0 transition-opacity group-hover:opacity-100"
                title="删除该历史图片（移入回收站）"
                @click.stop="removeImage(item.module, path)"
              >
                <Trash2 :size="12" />
              </span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="handleFileChange" />
  </div>
</template>
