<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { CalendarCheck2, Info, Puzzle, Sparkles } from 'lucide-vue-next'
import { portalApi, skillApi } from '../../api'
import { useUserStore } from '../../stores/user'
import { usePortalStore } from '../../stores/portal'
import StatCard from '../../components/StatCard.vue'
import AppLinkCard from '../../components/AppLinkCard.vue'
import AnnouncementDetail from '../../components/AnnouncementDetail.vue'
import SkillCard from '../../components/SkillCard.vue'
import { useSkillCategories } from '../../composables/useSkillCategories'
import type { AnnouncementItem, AppLinkItem, PortalThemeConfig, SkillItem, SummaryStat } from '../../types'

const router = useRouter()
const store = useUserStore()
const portalStore = usePortalStore()
const { load: loadSkillCategories, functionName, typeName } = useSkillCategories()

const summary = ref<SummaryStat>({ unclock: 0, late: 0, pending: 0 })
const collabLinks = ref<AppLinkItem[]>([])
const customLinks = ref<AppLinkItem[]>([])
const announcements = ref<AnnouncementItem[]>([])
/** 问候与统计区的可配置背景图，未配置时为 null；来自 portal store 缓存，回首页同步可读避免闪烁 */
const greetingTheme = computed<PortalThemeConfig | null>(() => portalStore.theme?.greeting ?? null)

/** 公告详情弹窗（与公告页共用同一组件） */
const detailRef = ref<InstanceType<typeof AnnouncementDetail>>()
const openDetail = (item: AnnouncementItem) => detailRef.value?.open(item)

/** AI Skill 推荐：取最新提交的 4 个已上架 Skill，无数据时整块隐藏 */
const recommendedSkills = ref<SkillItem[]>([])
const goSkillPlaza = () => router.push('/skills')

const nickname = computed(() => store.profile?.nickname || store.profile?.username || '同事')

const greeting = computed(() => {
  const hour = new Date().getHours()
  if (hour < 6) return '夜深了'
  if (hour < 9) return '早上好'
  if (hour < 12) return '上午好'
  if (hour < 14) return '中午好'
  if (hour < 18) return '下午好'
  return '晚上好'
})

onMounted(() => {
  portalApi
    .summary()
    .then((res) => (summary.value = res.data))
    .catch(console.error)
  portalApi
    .home()
    .then((res) => {
      collabLinks.value = res.data.links.collab
      customLinks.value = res.data.links.custom
      announcements.value = res.data.announcements
    })
    .catch(console.error)
  portalStore.ensureTheme().catch(console.error)
  loadSkillCategories()
    .then(() => skillApi.list({ page: 1, size: 4, status: 1, sort: 'newest' }))
    .then((res) => (recommendedSkills.value = res.data.list))
    .catch(console.error)
})

const formatDate = (value: string | null) => (value ? value.slice(0, 10) : '')

// 协作与办公保持 3×3 栅格，最多展示 9 个链接（超出部分不渲染）
const MAX_VISIBLE_COLLAB_LINKS = 9
const visibleCollabLinks = computed(() => collabLinks.value.slice(0, MAX_VISIBLE_COLLAB_LINKS))

// 公告区最多展示 6 条，使右栏高度与左侧协作区底部对齐
const MAX_VISIBLE_ANNOUNCEMENTS = 6
const visibleAnnouncements = computed(() => announcements.value.slice(0, MAX_VISIBLE_ANNOUNCEMENTS))
</script>

<template>
  <div class="mx-auto w-full max-w-[1920px] px-8 pb-16">
    <!-- 问候区 + 考勤统计：管理员可配置背景图的区域 -->
    <div class="relative -mx-8 px-8 pt-10">
      <!-- 背景图层：负外边距使其横向铺满，透明度由后台配置 -->
      <div
        v-if="greetingTheme"
        class="pointer-events-none absolute inset-x-0 top-0 -bottom-[35px] overflow-hidden"
        aria-hidden="true"
      >
        <img
          :src="greetingTheme.image_path"
          class="h-full w-full object-cover"
          :style="{ opacity: greetingTheme.opacity / 100 }"
          alt=""
        />
      </div>

      <div class="relative">
        <!-- 问候区 -->
        <section class="pb-8">
          <h2 class="text-[32px] font-semibold leading-snug text-ink">
            {{ greeting }}，{{ nickname }}
          </h2>
          <p class="mt-2 text-[15px] text-ink-sub">欢迎回来，准备好开始新的一天了吗？</p>
        </section>

        <!-- 考勤统计（固定尺寸小卡片，不随容器拉伸） -->
        <section class="flex flex-wrap gap-4">
          <StatCard label="未打卡" :value="summary.unclock" icon="CalendarX" tone="orange" />
          <StatCard label="迟到" :value="summary.late" icon="Timer" tone="green" />
          <StatCard label="待审批" :value="summary.pending" icon="ClipboardList" tone="blue" />
        </section>
      </div>
    </div>

    <!-- 协作与办公 + 公告与通知 双栏 -->
    <section v-if="collabLinks.length || announcements.length" class="mt-10 grid grid-cols-1 gap-8 lg:grid-cols-5">
      <!-- 协作与办公 -->
      <div v-if="collabLinks.length" class="flex flex-col lg:col-span-3">
        <div class="flex flex-1 flex-col rounded-2xl bg-white p-5 shadow-card">
          <div class="mb-4 flex items-center gap-1.5">
            <h3 class="text-lg font-bold text-ink">协作与办公</h3>
            <Info :size="16" class="text-ink-mute" />
          </div>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <AppLinkCard v-for="link in visibleCollabLinks" :key="link.id" :link="link" />
          </div>
        </div>
      </div>

      <!-- 公告与通知（限 6 条，容器拉伸至与左栏底部对齐） -->
      <div v-if="visibleAnnouncements.length" class="flex flex-col lg:col-span-2">
        <div class="flex flex-1 flex-col rounded-2xl bg-white p-5 shadow-card">
          <div class="mb-3 flex items-center gap-1.5">
            <h3 class="text-lg font-bold text-ink">公告与通知</h3>
            <Info :size="16" class="text-ink-mute" />
          </div>
          <div class="overflow-hidden">
            <div
              v-for="(item, index) in visibleAnnouncements"
              :key="item.id"
              class="group flex cursor-pointer items-center justify-between gap-4 py-3 transition-colors duration-200 hover:bg-[#F6F8FE]"
              :class="index > 0 ? 'border-t border-[#F0F3FA]' : ''"
              @click="openDetail(item)"
            >
              <div class="truncate text-[15px] font-medium text-primary-deep">
                <span
                  v-if="item.is_top === 1"
                  class="mr-1.5 rounded bg-[#FFF1F0] px-1.5 py-0.5 align-middle text-[12px] font-semibold text-[#F5222D]"
                >
                  置顶
                </span>
                {{ item.title }}
              </div>
              <span class="tabular shrink-0 text-[13px] text-ink-mute">{{ formatDate(item.published_at) }}</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 自定义工具（管理员配置后才显示） -->
    <section v-if="customLinks.length" class="mt-10">
      <div class="rounded-2xl bg-white p-5 shadow-card">
        <div class="mb-4 flex items-center gap-1.5">
          <h3 class="text-lg font-bold text-ink">自定义工具</h3>
          <Info :size="16" class="text-ink-mute" />
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <AppLinkCard v-for="link in customLinks" :key="link.id" :link="link" size="lg" />
        </div>
      </div>
    </section>

    <!-- AI Skill 推荐（有已上架 Skill 时才显示；卡片用紧凑布局，整体高度较常规卡片更低） -->
    <section v-if="recommendedSkills.length" class="mt-8">
      <div class="rounded-2xl bg-white p-4 shadow-card">
        <div class="mb-3 flex items-center justify-between">
          <div class="flex items-center gap-1.5">
            <Sparkles :size="18" class="text-primary" />
            <h3 class="text-lg font-bold text-ink">AI Skill 推荐</h3>
            <span class="text-[13px] text-ink-mute">按最新提交排序</span>
          </div>
          <button
            type="button"
            class="cursor-pointer text-[13px] text-primary-deep transition-colors hover:text-primary"
            @click="goSkillPlaza"
          >
            查看全部 →
          </button>
        </div>
        <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
          <SkillCard
            v-for="skill in recommendedSkills"
            :key="skill.id"
            :skill="skill"
            :function-name="functionName(skill.function_category_id)"
            :type-name="typeName(skill.type_category_id)"
            compact
            @detail="goSkillPlaza"
          />
        </div>
      </div>
    </section>

    <!-- 全部区块为空时的引导 -->
    <section
      v-if="!collabLinks.length && !customLinks.length && !announcements.length && !recommendedSkills.length"
      class="mt-16 flex flex-col items-center rounded-3xl border border-dashed border-[#C9D4EE] bg-white py-16 text-center"
    >
      <CalendarCheck2 :size="40" class="text-ink-mute" />
      <p class="mt-4 text-[15px] text-ink-sub">门户内容尚未配置</p>
      <p class="mt-1 text-[13px] text-ink-mute">管理员在后台配置应用链接与公告后，将在这里展示</p>
    </section>

    <!-- 公告详情弹窗 -->
    <AnnouncementDetail ref="detailRef" />
  </div>
</template>
