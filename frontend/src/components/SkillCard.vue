<script setup lang="ts">
import { computed } from 'vue'
import LucideIcon from './LucideIcon.vue'
import SkillLikeButton from './SkillLikeButton.vue'
import { useSkillLike } from '../composables/useSkillLike'
import { relativeTime } from '../utils/time'
import type { SkillItem } from '../types'

const props = defineProps<{
  skill: SkillItem
  /** 职能分类名称，父级按 id 查表后传入 */
  functionName: string
  /** 类型分类名称 */
  typeName: string
  /** 紧凑布局：图标居左、名称置于图标右侧，用于首页推荐等纵向空间受限的场景 */
  compact?: boolean
}>()

const emit = defineEmits<{ (e: 'detail', skill: SkillItem): void }>()

const { toggleLike } = useSkillLike()

const updatedText = computed(() => relativeTime(props.skill.updated_at))
</script>

<template>
  <!-- 紧凑卡片：图标 + 名称横向排布，整体高度约为标准卡的一半 -->
  <div
    v-if="compact"
    class="group flex h-full cursor-pointer flex-col rounded-xl border border-[#EEF2FB] bg-white p-4 shadow-card transition-all duration-200 hover:-translate-y-0.5 hover:border-[#DCE7FD] hover:shadow-card-hover"
    @click="emit('detail', skill)"
  >
    <div class="flex items-start gap-2.5">
      <div
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-primary-purple text-white shadow-sm transition-transform duration-200 group-hover:scale-105"
      >
        <LucideIcon :name="skill.icon" :size="19" />
      </div>
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-1.5">
          <h4 class="truncate text-[15px] font-semibold leading-snug text-ink">{{ skill.name }}</h4>
          <span class="shrink-0 rounded bg-[#F0F4FD] px-1.5 py-0.5 text-[11px] font-medium text-primary-deep">
            {{ functionName }}
          </span>
        </div>
        <p class="mt-1 line-clamp-2 text-[12px] leading-snug text-ink-sub">{{ skill.summary }}</p>
      </div>
    </div>

    <div class="mt-auto flex items-center justify-between gap-2 pt-3">
      <span class="truncate rounded bg-[#F4F1FE] px-1.5 py-0.5 text-[11px] text-primary-purple">{{ typeName }}</span>
      <div class="flex shrink-0 items-center gap-2">
        <span class="tabular text-[11px] text-ink-mute">{{ skill.owner_name }}</span>
        <SkillLikeButton :skill="skill" @toggle="toggleLike" />
      </div>
    </div>
  </div>

  <!-- 标准卡片：广场等宽绰区域使用 -->
  <div
    v-else
    class="group flex h-full cursor-pointer flex-col rounded-2xl border border-[#EEF2FB] bg-white p-5 shadow-card transition-all duration-200 hover:-translate-y-1 hover:border-[#DCE7FD] hover:shadow-card-hover"
    @click="emit('detail', skill)"
  >
    <div class="flex items-start justify-between">
      <div
        class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-primary-purple text-white shadow-md transition-transform duration-200 group-hover:scale-105"
      >
        <LucideIcon :name="skill.icon" :size="22" />
      </div>
      <span class="rounded-full bg-[#F0F4FD] px-2.5 py-1 text-xs font-medium text-primary-deep">
        {{ functionName }}
      </span>
    </div>

    <h4 class="mt-3.5 text-[16px] font-semibold leading-snug text-ink">{{ skill.name }}</h4>
    <p class="mt-1.5 line-clamp-2 text-[13px] leading-relaxed text-ink-sub">{{ skill.summary }}</p>

    <div class="mt-auto flex items-center justify-between gap-2 pt-3.5">
      <span class="rounded-md bg-[#F4F1FE] px-2 py-0.5 text-xs text-primary-purple">{{ typeName }}</span>
      <div class="flex shrink-0 items-center gap-2">
        <span class="tabular text-xs text-ink-mute">{{ skill.owner_name }} · {{ updatedText }}</span>
        <SkillLikeButton :skill="skill" size="md" @toggle="toggleLike" />
      </div>
    </div>
  </div>
</template>
