<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, RotateCw } from 'lucide-vue-next'
import IconPicker from '../../components/IconPicker.vue'
import LucideIcon from '../../components/LucideIcon.vue'
import { skillCategoryApi } from '../../api'
import type { SkillCategory, SkillCategoryDimension, SkillCategoryPayload } from '../../types'

const TABS = [
  { label: '职能分类', value: 'function' as const },
  { label: '类型分类', value: 'type' as const },
]

const dimension = ref<SkillCategoryDimension>('function')
const loading = ref(false)
const errorMsg = ref('')
const list = ref<SkillCategory[]>([])

const fetchList = () => {
  loading.value = true
  errorMsg.value = ''
  skillCategoryApi
    .list(dimension.value)
    .then((res) => (list.value = res.data))
    .catch(() => {
      errorMsg.value = '分类加载失败，请检查网络后重试'
    })
    .finally(() => (loading.value = false))
}

onMounted(fetchList)

// ---------- 新增 / 编辑 ----------

const dialogVisible = ref(false)
const dialogTitle = ref('新增分类')
const editingId = ref<number | null>(null)
const form = reactive<SkillCategoryPayload>({
  name: '',
  dimension: 'function',
  icon: 'Tag',
  sort: 10,
  status: 1,
})

const openCreate = () => {
  editingId.value = null
  dialogTitle.value = `新增${dimension.value === 'function' ? '职能' : '类型'}分类`
  Object.assign(form, { name: '', dimension: dimension.value, icon: 'Tag', sort: (list.value.length + 1) * 10, status: 1 })
  dialogVisible.value = true
}

const openEdit = (row: SkillCategory) => {
  editingId.value = row.id
  dialogTitle.value = `编辑${dimension.value === 'function' ? '职能' : '类型'}分类`
  Object.assign(form, { name: row.name, dimension: row.dimension, icon: row.icon, sort: row.sort, status: row.status })
  dialogVisible.value = true
}

const submit = () => {
  if (!form.name.trim()) {
    ElMessage.warning('请填写分类名称')
    return
  }
  const payload: SkillCategoryPayload = { ...form, name: form.name.trim() }
  const action = editingId.value ? skillCategoryApi.update(editingId.value, payload) : skillCategoryApi.create(payload)
  action
    .then(() => {
      ElMessage.success(editingId.value ? '保存成功' : '新增成功')
      dialogVisible.value = false
      fetchList()
    })
    .catch((err: Error) => ElMessage.error(err.message || '保存失败，请稍后重试'))
}

// ---------- 启停 / 删除 ----------

const toggleStatus = (row: SkillCategory) => {
  skillCategoryApi
    .update(row.id, { name: row.name, dimension: row.dimension, icon: row.icon, sort: row.sort, status: row.status })
    .then(() => ElMessage.success(row.status === 1 ? '已启用，将在筛选与上传表单中出现' : '已停用，不再出现在可选列表中'))
    .catch((err: Error) => {
      row.status = row.status === 1 ? 0 : 1
      ElMessage.error(err.message || '操作失败，请稍后重试')
    })
}

const remove = (row: SkillCategory) => {
  ElMessageBox.confirm(`确定删除分类「${row.name}」吗？该分类下仍有 Skill 时将无法删除。`, '删除分类', {
    type: 'warning',
  })
    .then(() =>
      skillCategoryApi.remove(row.id).then(() => {
        ElMessage.success('删除成功')
        fetchList()
      }),
    )
    .catch(() => undefined)
}

const dimensionHint = computed(() =>
  dimension.value === 'function'
    ? '职能分类用于按部门/岗位归档，如研发、人事、财务'
    : '类型分类用于按能力形态归档，如文档处理、数据分析、代码助手',
)
</script>

<template>
  <div class="rounded-2xl bg-white p-6 shadow-card">
    <div class="mb-5 flex items-center justify-between">
      <el-tabs v-model="dimension" class="section-tabs" @tab-change="fetchList">
        <el-tab-pane v-for="tab in TABS" :key="tab.value" :label="tab.label" :name="tab.value" />
      </el-tabs>
      <el-button type="primary" @click="openCreate">
        <Plus :size="15" class="mr-1" />新增分类
      </el-button>
    </div>

    <p class="mb-4 text-xs text-ink-mute">{{ dimensionHint }}</p>

    <div v-if="errorMsg" class="flex flex-col items-center py-14">
      <p class="text-[15px] text-[#F5222D]">{{ errorMsg }}</p>
      <el-button class="mt-4" type="primary" plain @click="fetchList">
        <RotateCw :size="14" class="mr-1" />重新加载
      </el-button>
    </div>

    <el-table v-else v-loading="loading" :data="list" stripe>
      <el-table-column label="图标" width="80">
        <template #default="{ row }">
          <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#F0F4FD] text-primary-deep">
            <LucideIcon :name="row.icon" :size="18" />
          </div>
        </template>
      </el-table-column>
      <el-table-column prop="name" label="名称" min-width="140" />
      <el-table-column prop="sort" label="排序" width="90" />
      <el-table-column label="状态" width="130" align="left">
        <template #default="{ row }">
          <div class="flex items-center gap-2" style="padding-right: 16px">
            <el-switch
              v-model="row.status"
              :active-value="1"
              :inactive-value="0"
              @change="toggleStatus(row)"
            />
            <span class="text-xs" :class="row.status === 1 ? 'text-primary-deep' : 'text-ink-mute'">
              {{ row.status === 1 ? '启用' : '停用' }}
            </span>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="150" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
          <el-button link type="danger" @click="remove(row)">删除</el-button>
        </template>
      </el-table-column>

      <template #empty>
        <div class="flex flex-col items-center py-14">
          <p class="text-[15px] text-ink-sub">该维度下还没有分类</p>
          <p class="mt-1 text-[13px] text-ink-mute">先新增分类，员工上传 Skill 时才能选择归档位置</p>
        </div>
      </template>
    </el-table>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="480px" destroy-on-close>
      <el-form label-width="80px">
        <el-form-item label="名称" required>
          <el-input v-model="form.name" placeholder="如：研发 / 文档处理" maxlength="12" show-word-limit />
        </el-form-item>
        <el-form-item label="图标">
          <IconPicker v-model="form.icon" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort" :min="0" :max="999" />
          <span class="ml-2 text-xs text-ink-mute">数字越小越靠前</span>
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="停用" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.section-tabs :deep(.el-tabs__header) {
  margin-bottom: 0;
}
</style>
