<script setup lang="ts">
import { computed } from 'vue'
import LucideIcon from './LucideIcon.vue'
import type { AppLinkItem } from '../types'

const props = withDefaults(defineProps<{ link: AppLinkItem; size?: 'md' | 'lg' }>(), { size: 'md' })

const iconColor = computed(() => {
  const map: Record<string, string> = {
    BookOpen: '#FA8C16',
    ClipboardCheck: '#5B8FF9',
    Users: '#5B8FF9',
    CalendarDays: '#52C41A',
    KanbanSquare: '#5B8FF9',
    GitBranch: '#FA8C16',
    FolderOpen: '#5B8FF9',
    BarChart3: '#5B8FF9',
    Mail: '#5B8FF9',
    Ticket: '#FA8C16',
    FileText: '#7A6FF0',
    MessageSquare: '#52C41A',
    GraduationCap: '#FA8C16',
  }
  return map[props.link.icon] || '#5B8FF9'
})

// 大号变体用于「自定义工具」，在铺满布局下保持卡片比例饱满
const isLarge = computed(() => props.size === 'lg')
const iconPx = computed(() => (isLarge.value ? 28 : 22))

const open = (url: string) => {
  window.open(url, '_blank', 'noopener,noreferrer')
}
</script>

<template>
  <button
    type="button"
    class="group flex items-center rounded-2xl bg-[#F7F9FD] text-left transition-colors duration-200 hover:bg-[#EEF3FE] cursor-pointer"
    :class="isLarge ? 'gap-5 px-6 py-6' : 'gap-4 px-5 py-5'"
    @click="open(link.url)"
  >
    <div
      class="flex shrink-0 items-center justify-center bg-[#F6F8FE] transition-colors duration-200 group-hover:bg-[#EEF2FB]"
      :class="isLarge ? 'h-14 w-14 rounded-2xl' : 'h-11 w-11 rounded-xl'"
      :style="{ color: iconColor }"
    >
      <LucideIcon :name="link.icon" :size="iconPx" />
    </div>
    <div class="min-w-0">
      <div class="truncate font-medium text-ink" :class="isLarge ? 'text-base' : 'text-[15px]'">
        {{ link.name }}
      </div>
      <div class="mt-0.5 line-clamp-1 text-ink-mute" :class="isLarge ? 'text-sm' : 'text-xs'">
        {{ link.subtitle }}
      </div>
    </div>
  </button>
</template>
