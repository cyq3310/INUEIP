<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { ElMessage, type FormInstance, type FormRules, type UploadFile } from 'element-plus'
import { CheckCircle2, Package, Plus, Trash2, UploadCloud, XCircle } from 'lucide-vue-next'
import IconPicker from './IconPicker.vue'
import LucideIcon from './LucideIcon.vue'
import { skillApi } from '../api'
import type { SkillCategory, SkillItem, SkillParam, SkillPayload, SkillZipParseResult } from '../types'

const props = defineProps<{
  modelValue: boolean
  /** 传入则为编辑，null 为新建 */
  skill: SkillItem | null
  functionCategories: SkillCategory[]
  typeCategories: SkillCategory[]
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'saved'): void
}>()

const visible = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})

const isEdit = computed(() => !!props.skill)
const dialogTitle = computed(() => (isEdit.value ? `编辑 Skill · ${props.skill?.name ?? ''}` : '上传 Skill'))

/** form = 在线表单创建，zip = 压缩包上传 */
const mode = ref<'form' | 'zip'>('form')

const createEmpty = () => ({
  name: '',
  icon: 'Sparkles',
  summary: '',
  content: '',
  function_category_id: '' as number | '',
  type_category_id: '' as number | '',
  params: [] as SkillParam[],
  source: 'form' as SkillItem['source'],
  status: 1 as SkillItem['status'],
  package_path: null as string | null,
})

const formRef = ref<FormInstance>()
const saving = ref(false)
const form = reactive(createEmpty())

const rules: FormRules = {
  name: [{ required: true, message: '请输入 Skill 名称', trigger: 'blur' }],
  function_category_id: [{ required: true, message: '请选择职能分类', trigger: 'change' }],
  type_category_id: [{ required: true, message: '请选择类型分类', trigger: 'change' }],
  summary: [{ required: true, message: '请填写一句话描述', trigger: 'blur' }],
  content: [{ required: true, message: '请填写提示词正文（SKILL.md）', trigger: 'blur' }],
}

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return
    mode.value = 'form'
    zipResult.value = null
    const skill = props.skill
    Object.assign(
      form,
      skill
        ? {
            name: skill.name,
            icon: skill.icon,
            summary: skill.summary,
            content: skill.content,
            function_category_id: skill.function_category_id,
            type_category_id: skill.type_category_id,
            params: skill.params.map((p) => ({ ...p })),
            source: skill.source,
            status: skill.status,
            package_path: skill.package_path,
          }
        : createEmpty(),
    )
    formRef.value?.clearValidate()
  },
)

// ---------- 参数列表 ----------

const addParam = () => form.params.push({ name: '', label: '', required: true })
const removeParam = (index: number) => form.params.splice(index, 1)

// ---------- 压缩包模式 ----------

const parsing = ref(false)
const zipResult = ref<SkillZipParseResult | null>(null)

const handleZip = (file: UploadFile) => {
  const raw = file.raw
  if (!raw) return
  zipResult.value = null
  parsing.value = true
  skillApi
    .parseZip(raw)
    .then((res) => {
      zipResult.value = res.data
      if (res.data.valid) {
        ElMessage.success('压缩包校验通过，请确认后回填表单')
      } else {
        ElMessage.error('压缩包校验未通过，请按提示修正后重新上传')
      }
    })
    .catch((err: Error) => ElMessage.error(err.message || '压缩包解析失败，请稍后重试'))
    .finally(() => (parsing.value = false))
}

/** 校验通过后把解析结果回填到表单，再切回表单模式继续编辑 */
const applyDraft = () => {
  const draft = zipResult.value?.draft
  if (!draft) return
  form.name = draft.name
  form.summary = draft.summary
  form.content = draft.content
  form.params = draft.params.map((p) => ({ ...p }))
  form.icon = draft.icon
  form.source = 'zip'
  // 取服务端落盘后的路径（真实接口）；mock 下回落到文件名
  form.package_path = zipResult.value?.package_path ?? zipResult.value?.file_name ?? null
  mode.value = 'form'
  ElMessage.success('已回填表单，请确认分类与参数后提交')
}

// ---------- 提交 ----------

const submit = async (status: SkillItem['status']) => {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return

  saving.value = true
  const payload: SkillPayload = {
    name: form.name.trim(),
    icon: form.icon || 'Sparkles',
    summary: form.summary.trim(),
    function_category_id: Number(form.function_category_id),
    type_category_id: Number(form.type_category_id),
    content: form.content,
    params: form.params.filter((p) => p.name.trim()),
    source: form.source,
    status,
    package_path: form.package_path,
  }

  const action = props.skill ? skillApi.update(props.skill.id, payload) : skillApi.create(payload)
  action
    .then(() => {
      ElMessage.success(status === 1 ? '已保存并上架' : '已保存为草稿')
      visible.value = false
      emit('saved')
    })
    .catch((err: Error) => ElMessage.error(err.message || '保存失败，请稍后重试'))
    .finally(() => (saving.value = false))
}
</script>

<template>
  <el-dialog v-model="visible" :title="dialogTitle" width="680px" destroy-on-close>
    <!-- 模式切换：编辑态不再支持换包，仅展示表单模式 -->
    <div v-if="!isEdit" class="mb-4 flex items-center justify-between">
      <div class="inline-flex rounded-xl bg-[#F2F5FC] p-1">
        <button
          type="button"
          class="flex h-8 cursor-pointer items-center gap-1.5 rounded-lg px-4 text-[13px] transition-all duration-200"
          :class="mode === 'form' ? 'bg-white font-medium text-primary-deep shadow-sm' : 'text-ink-sub'"
          @click="mode = 'form'"
        >
          <Plus :size="14" />表单创建
        </button>
        <button
          type="button"
          class="flex h-8 cursor-pointer items-center gap-1.5 rounded-lg px-4 text-[13px] transition-all duration-200"
          :class="mode === 'zip' ? 'bg-white font-medium text-primary-deep shadow-sm' : 'text-ink-sub'"
          @click="mode = 'zip'"
        >
          <Package :size="14" />压缩包上传
        </button>
      </div>
      <span v-if="mode === 'zip'" class="text-xs text-ink-mute">支持 .zip，需含 SKILL.md，单包 ≤ 50MB</span>
    </div>

    <!-- 压缩包上传 -->
    <div v-if="mode === 'zip'" class="space-y-4">
      <el-upload drag :auto-upload="false" :show-file-list="false" accept=".zip" :on-change="handleZip">
        <div class="flex flex-col items-center py-6">
          <UploadCloud :size="30" class="text-primary" />
          <p class="mt-2 text-[14px] text-ink">将压缩包拖到此处，或<span class="text-primary-deep">点击上传</span></p>
          <p class="mt-1 text-xs text-ink-mute">服务端会解析目录结构并返回逐项校验结论</p>
        </div>
      </el-upload>

      <div v-if="parsing" class="rounded-xl bg-[#F7F9FE] px-4 py-6 text-center text-[13px] text-ink-sub">
        正在解析压缩包结构…
      </div>

      <div v-else-if="zipResult" class="rounded-xl border border-[#EEF2FB] p-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <component :is="zipResult.valid ? CheckCircle2 : XCircle" :size="17" :class="zipResult.valid ? 'text-[#19BE6B]' : 'text-[#F5222D]'" />
            <span class="text-[14px] font-medium text-ink">{{ zipResult.valid ? '校验通过' : '校验未通过' }}</span>
          </div>
          <span class="tabular text-xs text-ink-mute">{{ zipResult.file_name }} · {{ (zipResult.size / 1024).toFixed(1) }} KB</span>
        </div>

        <ul class="mt-3 space-y-2">
          <li v-for="check in zipResult.checks" :key="check.label" class="flex items-start gap-2 text-[13px]">
            <component :is="check.passed ? CheckCircle2 : XCircle" :size="15" class="mt-0.5" :class="check.passed ? 'text-[#19BE6B]' : 'text-[#F5222D]'" />
            <div>
              <span class="text-ink">{{ check.label }}</span>
              <span v-if="check.required" class="ml-1 text-xs text-[#F5222D]">必填</span>
              <p class="text-xs text-ink-mute">{{ check.message }}</p>
            </div>
          </li>
        </ul>

        <div class="mt-3 rounded-lg bg-[#F7F9FE] px-3 py-2 font-mono text-[12px] leading-relaxed text-ink-sub">
          <div v-for="entry in zipResult.entries" :key="entry.path">{{ entry.path }}</div>
        </div>

        <el-button v-if="zipResult.valid" type="primary" class="mt-3 w-full" @click="applyDraft">
          回填表单并继续编辑
        </el-button>
      </div>
    </div>

    <!-- 在线表单 -->
    <el-form v-else ref="formRef" :model="form" :rules="rules" label-width="96px">
      <div class="grid grid-cols-2 gap-x-4">
        <el-form-item label="名称" prop="name">
          <el-input v-model="form.name" placeholder="如：需求文档自动拆解" maxlength="40" show-word-limit />
        </el-form-item>
        <el-form-item label="图标">
          <IconPicker v-model="form.icon" />
        </el-form-item>
        <el-form-item label="职能分类" prop="function_category_id">
          <el-select v-model="form.function_category_id" placeholder="按职能归档" class="w-full">
            <el-option v-for="c in functionCategories" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="类型分类" prop="type_category_id">
          <el-select v-model="form.type_category_id" placeholder="按能力类型归档" class="w-full">
            <el-option v-for="c in typeCategories" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
      </div>

      <el-form-item label="描述" prop="summary">
        <el-input v-model="form.summary" placeholder="一句话说明这个 Skill 解决什么问题" maxlength="60" show-word-limit />
      </el-form-item>

      <el-form-item label="提示词正文" prop="content">
        <el-input v-model="form.content" type="textarea" :rows="7" placeholder="等价于 SKILL.md 正文，描述角色、任务、流程与输出格式" />
      </el-form-item>

      <el-form-item label="参数列表">
        <div class="w-full rounded-xl border border-[#EEF2FB]">
          <div class="flex items-center justify-between bg-[#FAFBFE] px-3 py-2">
            <span class="text-[13px] text-ink-sub">运行时需要使用者填写的入参</span>
            <button
              type="button"
              class="flex cursor-pointer items-center gap-1 text-[13px] text-primary-deep transition-colors hover:text-primary"
              @click="addParam"
            >
              <Plus :size="14" />添加参数
            </button>
          </div>

          <p v-if="!form.params.length" class="px-3 py-4 text-center text-[13px] text-ink-mute">
            暂无入参，该 Skill 将直接运行
          </p>

          <div
            v-for="(param, index) in form.params"
            :key="index"
            class="flex items-center gap-2 border-t border-[#F2F5FC] px-3 py-2"
          >
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#F0F4FD] text-primary-deep">
              <LucideIcon name="SlidersHorizontal" :size="15" />
            </div>
            <el-input v-model="param.name" placeholder="参数名，如 doc_url" class="!w-[150px]" />
            <el-input v-model="param.label" placeholder="说明，如 需求文档链接" />
            <div class="flex w-[110px] shrink-0 items-center gap-2">
              <el-switch v-model="param.required" />
              <span class="text-xs" :class="param.required ? 'text-ink-sub' : 'text-ink-mute'">
                {{ param.required ? '必填' : '可选' }}
              </span>
            </div>
            <button
              type="button"
              aria-label="删除该参数"
              class="flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-ink-mute transition-colors hover:bg-[#FFF1F0] hover:text-[#F5222D]"
              @click="removeParam(index)"
            >
              <Trash2 :size="15" />
            </button>
          </div>
        </div>
      </el-form-item>
    </el-form>

    <template #footer>
      <div class="flex justify-end gap-2">
        <el-button @click="visible = false">取消</el-button>
        <el-button :loading="saving" @click="submit(0)">保存为草稿</el-button>
        <el-button type="primary" :loading="saving" @click="submit(1)">保存并上架</el-button>
      </div>
    </template>
  </el-dialog>
</template>
