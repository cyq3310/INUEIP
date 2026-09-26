<script setup lang="ts">
import { Heart } from 'lucide-vue-next'
import type { SkillItem } from '../types'

withDefaults(
  defineProps<{
    skill: SkillItem
    /** sm 用于首页紧凑卡片，md 用于标准卡片与详情抽屉 */
    size?: 'sm' | 'md'
    disabled?: boolean
  }>(),
  { size: 'sm', disabled: false },
)

const emit = defineEmits<{ (e: 'toggle', skill: SkillItem): void }>()
</script>

<template>
  <button
    type="button"
    :disabled="disabled"
    :title="skill.liked ? '取消点赞' : '点赞'"
    :aria-pressed="skill.liked"
    class="flex shrink-0 cursor-pointer items-center gap-1 rounded-full px-2 py-0.5 transition-colors duration-200 disabled:cursor-not-allowed disabled:opacity-60"
    :class="[
      size === 'md' ? 'text-[13px]' : 'text-[11px]',
      skill.liked
        ? 'bg-[#FFF1F0] text-[#F5222D]'
        : 'bg-[#F4F6FB] text-ink-mute hover:bg-[#EAF0FD] hover:text-primary-deep',
    ]"
    @click.stop="emit('toggle', skill)"
  >
    <Heart :size="size === 'md' ? 15 : 13" :fill="skill.liked ? 'currentColor' : 'none'" />
    <span class="tabular">{{ skill.like_count }}</span>
  </button>
</template>
