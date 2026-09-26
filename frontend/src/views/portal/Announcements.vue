<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { Info, Megaphone, Search } from 'lucide-vue-next'
import { portalApi } from '../../api'
import AnnouncementDetail from '../../components/AnnouncementDetail.vue'
import type { AnnouncementItem } from '../../types'

const loading = ref(false)
const list = ref<AnnouncementItem[]>([])
const total = ref(0)
const query = reactive({ page: 1, size: 10, keyword: '' })

/** 公告详情弹窗（与首页共用同一组件） */
const detailRef = ref<InstanceType<typeof AnnouncementDetail>>()

const fetchList = () => {
  loading.value = true
  portalApi
    .announcements({ page: query.page, size: query.size, keyword: query.keyword.trim() || undefined })
    .then((res) => {
      list.value = res.data.list
      total.value = res.data.total
    })
    .catch(console.error)
    .finally(() => (loading.value = false))
}

const search = () => {
  query.page = 1
  fetchList()
}

const formatDate = (value: string | null) => (value ? value.slice(0, 10) : '')

/** 点击公告条目：打开详情弹窗 */
const openDetail = (item: AnnouncementItem) => detailRef.value?.open(item)

const subtitle = computed(() => `共 ${total.value} 条公告，置顶公告优先展示`)

onMounted(fetchList)
</script>

<template>
  <div class="mx-auto w-full max-w-[1920px] px-8 pb-16">
    <!-- 页头：标题 + 查询（同一行，标题左对齐） -->
    <section class="flex flex-wrap items-end justify-between gap-4 pt-10 pb-8">
      <div>
        <h2 class="text-[32px] font-semibold leading-snug text-ink">公告与通知</h2>
        <p class="mt-2 text-[15px] text-ink-sub">{{ subtitle }}</p>
      </div>
      <div class="flex items-center gap-3">
        <el-input
          v-model="query.keyword"
          placeholder="搜索公告标题"
          clearable
          class="!w-64"
          @keyup.enter="search"
          @clear="search"
        >
          <template #prefix><Search :size="15" /></template>
        </el-input>
        <el-button type="primary" plain @click="search">查询</el-button>
      </div>
    </section>

    <!-- 全部公告 -->
    <section class="rounded-2xl bg-white p-5 shadow-card">
      <div class="mb-3 flex items-center gap-1.5">
        <h3 class="text-lg font-bold text-ink">全部公告</h3>
        <Info :size="16" class="text-ink-mute" />
      </div>

      <div v-loading="loading" class="overflow-hidden">
        <div
          v-for="(item, index) in list"
          :key="item.id"
          class="group flex cursor-pointer items-center justify-between gap-4 py-3.5 transition-colors duration-200 hover:bg-[#F6F8FE]"
          :class="index > 0 ? 'border-t border-[#F0F3FA]' : ''"
          @click="openDetail(item)"
        >
          <div class="min-w-0">
            <div class="truncate text-[15px] font-medium text-primary-deep">
              <span
                v-if="item.is_top === 1"
                class="mr-1.5 rounded bg-[#FFF1F0] px-1.5 py-0.5 align-middle text-[12px] font-semibold text-[#F5222D]"
              >
                置顶
              </span>
              {{ item.title }}
            </div>
            <p v-if="item.summary" class="mt-1 truncate text-[13px] text-ink-sub">{{ item.summary }}</p>
          </div>
          <span class="tabular shrink-0 text-[13px] text-ink-mute">{{ formatDate(item.published_at) }}</span>
        </div>

        <!-- 空状态 -->
        <div v-if="!loading && !list.length" class="flex flex-col items-center py-16 text-center">
          <Megaphone :size="40" class="text-ink-mute" />
          <p class="mt-4 text-[15px] text-ink-sub">{{ query.keyword ? '未找到匹配的公告' : '暂无公告' }}</p>
        </div>
      </div>

      <!-- 分页：公告栏组下方常驻「上一页/下一页」 -->
      <div class="mt-4 flex justify-end">
        <el-pagination
          v-model:current-page="query.page"
          :page-size="query.size"
          :total="total"
          layout="total, prev, pager, next"
          @current-change="fetchList"
        />
      </div>
    </section>

    <!-- 公告详情弹窗 -->
    <AnnouncementDetail ref="detailRef" />
  </div>
</template>
