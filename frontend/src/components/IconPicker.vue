<script setup lang="ts">
import { computed, ref } from 'vue'
import { Search } from 'lucide-vue-next'
import LucideIcon from './LucideIcon.vue'
import { ICON_OPTIONS } from '../config/icon-options'

const props = withDefaults(
  defineProps<{ modelValue: string; size?: number; placeholder?: string }>(),
  { size: 18, placeholder: '点击右侧图标选择，或输入图标名' },
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
  (e: 'change', value: string): void
}>()

const visible = ref(false)
const keyword = ref('')
const draft = ref(props.modelValue)
/** 打开面板时的原始值，取消时回滚 */
const snapshot = ref(props.modelValue)

const value = computed({
  get: () => props.modelValue,
  set: (v: string) => emit('update:modelValue', v),
})

const filtered = computed(() => {
  const kw = keyword.value.trim().toLowerCase()
  if (!kw) return ICON_OPTIONS
  return ICON_OPTIONS.filter((item) => item.name.toLowerCase().includes(kw) || item.label.toLowerCase().includes(kw))
})

const open = () => {
  snapshot.value = props.modelValue
  draft.value = props.modelValue
  keyword.value = ''
  visible.value = true
}

/** 点选即回填，父级表单即时同步 */
const pick = (name: string) => {
  draft.value = name
  emit('update:modelValue', name)
}

const confirm = () => {
  visible.value = false
  emit('change', draft.value)
}

const cancel = () => {
  emit('update:modelValue', snapshot.value)
  visible.value = false
}
</script>

<template>
  <div class="flex w-full items-center gap-3">
    <el-input v-model="value" :placeholder="placeholder" />
    <button
      type="button"
      title="点击选择图标"
      aria-label="选择图标"
      class="flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg bg-[#F0F4FD] text-primary-deep transition duration-150 hover:bg-primary hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/50"
      @click="open"
    >
      <LucideIcon :name="modelValue" :size="size" />
    </button>

    <el-dialog v-model="visible" title="选择图标" width="620px" append-to-body destroy-on-close class="icon-picker-dialog">
      <el-input v-model="keyword" placeholder="搜索图标名称，如 知识库 / BookOpen" clearable>
        <template #prefix><Search :size="15" /></template>
      </el-input>

      <div class="mt-3 max-h-[320px] overflow-y-auto pr-1">
        <div v-if="!filtered.length" class="flex flex-col items-center justify-center gap-2 py-10 text-ink-mute">
          <Search :size="26" />
          <p class="text-xs">未找到匹配的图标，可直接在输入框输入图标名</p>
        </div>
        <div v-else class="grid grid-cols-6 gap-2 sm:grid-cols-8 md:grid-cols-10">
          <button
            v-for="item in filtered"
            :key="item.name"
            type="button"
            :title="`${item.label} · ${item.name}`"
            class="flex h-[68px] w-full flex-col items-center justify-center gap-1 rounded-lg border border-transparent transition duration-150 hover:bg-[#F0F4FD] hover:text-primary-deep"
            :class="draft === item.name ? 'bg-[#F0F4FD] text-primary-deep ring-2 ring-primary' : 'text-ink-sub'"
            @click="pick(item.name)"
          >
            <LucideIcon :name="item.name" :size="20" />
            <span class="w-full truncate px-1 text-[11px] leading-none">{{ item.label }}</span>
          </button>
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#F0F4FD] text-primary-deep">
              <LucideIcon :name="draft" :size="16" />
            </div>
            <span class="text-xs text-ink-sub">当前所选：{{ draft || '未选择' }}</span>
          </div>
          <div class="flex gap-2">
            <el-button @click="cancel">取消</el-button>
            <el-button type="primary" @click="confirm">确定</el-button>
          </div>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.icon-picker-dialog {
  max-width: 92vw;
}
</style>
