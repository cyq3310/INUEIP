<script setup lang="ts">
import { Sparkles } from 'lucide-vue-next'
import LucideIcon from './LucideIcon.vue'
import type { SkillCategory } from '../types'

const props = defineProps<{
  categories: SkillCategory[]
  /** 当前选中的分类 id，'' 表示全部 */
  modelValue: number | ''
  /** 各分类下的 Skill 数量，用于展示计数 */
  counts: Record<number, number>
  total: number
}>()

const emit = defineEmits<{ (e: 'update:modelValue', value: number | ''): void }>()

const isActive = (id: number | '') => props.modelValue === id
const select = (id: number | '') => emit('update:modelValue', id)
</script>

<template>
  <div class="flex flex-wrap items-center gap-2.5">
    <button
      type="button"
      class="flex h-9 cursor-pointer items-center gap-1.5 rounded-full px-4 text-sm transition-all duration-200"
      :class="
        isActive('')
          ? 'bg-gradient-to-r from-primary to-primary-purple font-medium text-white shadow-md'
          : 'border border-[#E6EBF5] bg-white text-ink-sub hover:border-[#BFD3FB] hover:text-primary-deep'
      "
      @click="select('')"
    >
      <Sparkles :size="15" />
      全部
      <span class="tabular text-xs opacity-80">{{ total }}</span>
    </button>

    <button
      v-for="item in categories"
      :key="item.id"
      type="button"
      class="flex h-9 cursor-pointer items-center gap-1.5 rounded-full px-4 text-sm transition-all duration-200"
      :class="
        isActive(item.id)
          ? 'bg-gradient-to-r from-primary to-primary-purple font-medium text-white shadow-md'
          : 'border border-[#E6EBF5] bg-white text-ink-sub hover:border-[#BFD3FB] hover:text-primary-deep'
      "
      @click="select(item.id)"
    >
      <LucideIcon :name="item.icon" :size="15" />
      {{ item.name }}
      <span class="tabular text-xs opacity-80">{{ counts[item.id] ?? 0 }}</span>
    </button>
  </div>
</template>
