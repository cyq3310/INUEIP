<script setup lang="ts">
import { computed } from 'vue'
import { ElMessage } from 'element-plus'
import { Copy } from 'lucide-vue-next'
import LucideIcon from './LucideIcon.vue'
import SkillLikeButton from './SkillLikeButton.vue'
import { useSkillLike } from '../composables/useSkillLike'
import { SKILL_SOURCE_META, SKILL_STATUS_META } from '../config/skill-meta'
import { relativeTime } from '../utils/time'
import { getRuntimeConfig } from '../config/runtime'
import type { SkillItem } from '../types'

const props = defineProps<{
  modelValue: boolean
  skill: SkillItem | null
  functionName: string
  typeName: string
}>()

const emit = defineEmits<{ (e: 'update:modelValue', value: boolean): void }>()

const { toggleLike } = useSkillLike()

const visible = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})

const statusMeta = computed(() =>
  props.skill ? SKILL_STATUS_META[props.skill.status] : SKILL_STATUS_META[0],
)

// 资源包存的是相对路径（如 skills/101/package.zip），展示时拼上 skill.local_path 前缀
const packageUrl = computed(() => {
  if (!props.skill?.package_path) return ''
  const base = getRuntimeConfig().skill.local_path.replace(/\/+$/, '')
  return base + '/' + props.skill.package_path
})

const copyContent = () => {
  if (!props.skill) return
  navigator.clipboard
    .writeText(props.skill.content)
    .then(() => ElMessage.success('提示词正文已复制到剪贴板'))
    .catch(() => ElMessage.warning('当前浏览器不支持自动复制，请手动选择正文复制'))
}
</script>

<template>
  <el-drawer v-model="visible" :size="560" destroy-on-close>
    <template #header>
      <div v-if="skill" class="flex w-full items-center gap-3">
        <div
          class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-primary-purple text-white shadow-md"
        >
          <LucideIcon :name="skill.icon" :size="22" />
        </div>
        <div class="min-w-0">
          <div class="flex items-center gap-2">
            <h3 class="truncate text-[17px] font-semibold text-ink">{{ skill.name }}</h3>
            <span class="flex items-center gap-1.5 text-xs" :class="statusMeta.text">
              <span class="h-2 w-2 rounded-full" :class="statusMeta.dot" />
              {{ statusMeta.label }}
            </span>
          </div>
          <p class="mt-0.5 text-xs text-ink-mute">
            {{ skill.owner_name }} 上传 · 更新于 {{ relativeTime(skill.updated_at) }}
          </p>
        </div>
        <SkillLikeButton class="ml-auto" :skill="skill" size="md" @toggle="toggleLike" />
      </div>
    </template>

    <div v-if="skill" class="flex flex-col gap-5">
      <div class="flex flex-wrap items-center gap-2">
        <span class="rounded-full bg-[#F0F4FD] px-2.5 py-1 text-xs font-medium text-primary-deep">
          {{ functionName }}
        </span>
        <span class="rounded-md bg-[#F4F1FE] px-2 py-0.5 text-xs text-primary-purple">{{ typeName }}</span>
        <span class="rounded-md px-2 py-0.5 text-xs" :class="SKILL_SOURCE_META[skill.source].cls">
          来源：{{ SKILL_SOURCE_META[skill.source].label }}
        </span>
      </div>

      <section>
        <h4 class="mb-2 text-[13px] font-medium text-ink-sub">一句话描述</h4>
        <p class="rounded-xl bg-[#F7F9FE] px-4 py-3 text-[14px] leading-relaxed text-ink">{{ skill.summary }}</p>
      </section>

      <section>
        <div class="mb-2 flex items-center justify-between">
          <h4 class="text-[13px] font-medium text-ink-sub">提示词正文（SKILL.md）</h4>
          <button
            type="button"
            class="flex cursor-pointer items-center gap-1 rounded-lg px-2 py-1 text-xs text-primary-deep transition-colors hover:bg-[#F0F4FD]"
            @click="copyContent"
          >
            <Copy :size="13" />复制
          </button>
        </div>
        <pre class="max-h-72 overflow-auto whitespace-pre-wrap rounded-xl bg-[#F7F9FE] px-4 py-3 text-[13px] leading-relaxed text-ink">{{ skill.content }}</pre>
      </section>

      <section>
        <h4 class="mb-2 text-[13px] font-medium text-ink-sub">运行参数</h4>
        <div v-if="skill.params.length" class="overflow-hidden rounded-xl border border-[#EEF2FB]">
          <div
            v-for="param in skill.params"
            :key="param.name"
            class="flex items-center justify-between gap-4 border-b border-[#F2F5FC] px-4 py-2.5 last:border-b-0"
          >
            <div class="min-w-0">
              <code class="text-[13px] text-primary-deep">{{ param.name }}</code>
              <span class="ml-2 text-[13px] text-ink-sub">{{ param.label }}</span>
            </div>
            <span class="shrink-0 text-xs" :class="param.required ? 'text-[#F5222D]' : 'text-ink-mute'">
              {{ param.required ? '必填' : '可选' }}
            </span>
          </div>
        </div>
        <p v-else class="rounded-xl bg-[#F7F9FE] px-4 py-3 text-[13px] text-ink-mute">该 Skill 无需额外入参，直接使用即可</p>
      </section>

      <section v-if="skill.package_path">
        <h4 class="mb-2 text-[13px] font-medium text-ink-sub">资源包</h4>
        <a
          :href="packageUrl"
          target="_blank"
          rel="noopener"
          class="block break-all rounded-xl bg-[#F7F9FE] px-4 py-3 font-mono text-[12px] text-primary-deep hover:underline"
        >
          {{ packageUrl }}
        </a>
      </section>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <el-button @click="visible = false">关闭</el-button>
        <el-button type="primary" @click="copyContent">
          <Copy :size="14" class="mr-1" />复制提示词
        </el-button>
      </div>
    </template>
  </el-drawer>
</template>
