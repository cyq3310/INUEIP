<script setup lang="ts">
import LucideIcon from './LucideIcon.vue'

defineProps<{
  label: string
  value: number
  icon: string
  tone: 'blue' | 'orange' | 'red' | 'green'
}>()

const toneClass: Record<string, string> = {
  blue: 'text-[#5B8FF9]',
  orange: 'text-[#FA8C16]',
  red: 'text-[#F5222D]',
  green: 'text-[#52C41A]',
}
</script>

<template>
  <div
    class="flex w-60 items-center gap-3 rounded-2xl bg-white px-4 py-3.5 shadow-card cursor-default"
  >
    <div class="flex h-8 w-8 items-center justify-center" :class="toneClass[tone]">
      <LucideIcon :name="icon" :size="18" />
    </div>
    <div>
      <div class="text-xs text-ink-sub">{{ label }}</div>
      <div class="tabular text-xl font-semibold text-ink leading-tight">{{ value }}</div>
    </div>
  </div>
</template>
