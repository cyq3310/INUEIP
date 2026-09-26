<script setup lang="ts">
import { computed } from 'vue'
import * as icons from 'lucide-vue-next'

const props = withDefaults(defineProps<{ name: string; size?: number }>(), { size: 24 })

const iconComponent = computed(() => {
  const map = icons as unknown as Record<string, object>
  return map[props.name] ?? icons.Link
})
</script>

<template>
  <component :is="iconComponent" :size="size" :stroke-width="1.8" />
</template>
