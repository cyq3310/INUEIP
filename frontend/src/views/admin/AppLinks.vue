<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search } from 'lucide-vue-next'
import { appLinkApi } from '../../api'
import { useUserStore } from '../../stores/user'
import LucideIcon from '../../components/LucideIcon.vue'
import IconPicker from '../../components/IconPicker.vue'
import type { AppLinkItem } from '../../types'

const store = useUserStore()

const loading = ref(false)
const list = ref<AppLinkItem[]>([])
const total = ref(0)
const query = reactive({ page: 1, size: 20, section: 'collab', keyword: '' })

const dialogVisible = ref(false)
const dialogTitle = ref('新增链接')
const editingId = ref<number | null>(null)
const form = reactive({ name: '', icon: 'Link', subtitle: '', url: '', section: 'collab', sort: 0, status: 1 })

const fetchList = () => {
  loading.value = true
  appLinkApi
    .list(query)
    .then((res) => {
      list.value = res.data.list
      total.value = res.data.total
    })
    .catch(console.error)
    .finally(() => (loading.value = false))
}

const switchSection = () => {
  query.page = 1
  fetchList()
}

const openCreate = () => {
  editingId.value = null
  dialogTitle.value = '新增链接'
  Object.assign(form, { name: '', icon: 'Link', subtitle: '', url: '', section: query.section, sort: 0, status: 1 })
  dialogVisible.value = true
}

const openEdit = (row: AppLinkItem) => {
  editingId.value = row.id
  dialogTitle.value = '编辑链接'
  Object.assign(form, {
    name: row.name,
    icon: row.icon,
    subtitle: row.subtitle,
    url: row.url,
    section: row.section ?? query.section,
    sort: row.sort ?? 0,
    status: row.status ?? 1,
  })
  dialogVisible.value = true
}

const submit = () => {
  const action = editingId.value ? appLinkApi.update(editingId.value, { ...form }) : appLinkApi.create({ ...form })
  action
    .then(() => {
      ElMessage.success(editingId.value ? '保存成功' : '新增成功')
      dialogVisible.value = false
      fetchList()
    })
    .catch(console.error)
}

/** 启停切换：后端为整表校验，需携带完整字段 */
const toggleStatus = (row: AppLinkItem) => {
  appLinkApi
    .update(row.id, {
      name: row.name,
      icon: row.icon,
      subtitle: row.subtitle,
      url: row.url,
      section: row.section,
      sort: row.sort,
      status: row.status,
    })
    .then(() => ElMessage.success(row.status === 1 ? '已启用，门户将显示该链接' : '已停用，门户将不再显示'))
    .catch((err) => {
      console.error(err)
      row.status = row.status === 1 ? 0 : 1
    })
}

const remove = (row: AppLinkItem) => {
  ElMessageBox.confirm(`确定删除链接「${row.name}」吗？删除后门户不再显示。`, '删除链接', { type: 'warning' })
    .then(() =>
      appLinkApi.remove(row.id).then(() => {
        ElMessage.success('删除成功')
        fetchList()
      }),
    )
    .catch(() => undefined)
}

onMounted(fetchList)
</script>

<template>
  <div class="rounded-2xl bg-white p-6 shadow-card">
    <div class="mb-5 flex items-center justify-between">
      <el-tabs v-model="query.section" class="section-tabs" @tab-change="switchSection">
        <el-tab-pane label="协作与办公" name="collab" />
        <el-tab-pane label="自定义工具" name="custom" />
      </el-tabs>
      <el-button v-if="store.hasPermission('app-link:create')" type="primary" @click="openCreate">
        <Plus :size="15" class="mr-1" />新增链接
      </el-button>
    </div>

    <div class="mb-4 flex items-center gap-3">
      <el-input v-model="query.keyword" placeholder="搜索名称 / 描述" clearable class="!w-64" @keyup.enter="fetchList">
        <template #prefix><Search :size="15" /></template>
      </el-input>
      <el-button type="primary" plain @click="fetchList">查询</el-button>
      <span class="text-xs text-ink-mute">仅「启用」状态的链接会在门户首页显示</span>
    </div>

    <el-table v-loading="loading" :data="list" stripe>
      <el-table-column label="图标" width="80">
        <template #default="{ row }">
          <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#F0F4FD] text-primary-deep">
            <LucideIcon :name="row.icon" :size="18" />
          </div>
        </template>
      </el-table-column>
      <el-table-column prop="name" label="名称" min-width="110" />
      <el-table-column prop="subtitle" label="副标题" min-width="200">
        <template #default="{ row }">{{ row.subtitle || '—' }}</template>
      </el-table-column>
      <el-table-column prop="url" label="链接地址" min-width="220" show-overflow-tooltip />
      <el-table-column prop="sort" label="排序" width="80" />
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-switch v-model="row.status" :active-value="1" :inactive-value="0" @change="toggleStatus(row)" />
        </template>
      </el-table-column>
      <el-table-column label="操作" width="150" fixed="right">
        <template #default="{ row }">
          <el-button v-if="store.hasPermission('app-link:update')" link type="primary" @click="openEdit(row)">编辑</el-button>
          <el-button v-if="store.hasPermission('app-link:delete')" link type="danger" @click="remove(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="mt-5 flex justify-end">
      <el-pagination v-model:current-page="query.page" :page-size="query.size" :total="total" layout="total, prev, pager, next" @change="fetchList" />
    </div>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="520px" destroy-on-close>
      <el-form label-width="90px">
        <el-form-item label="名称" required>
          <el-input v-model="form.name" placeholder="如：内部Wiki" />
        </el-form-item>
        <el-form-item label="图标">
          <IconPicker v-model="form.icon" />
        </el-form-item>
        <el-form-item label="副标题">
          <el-input v-model="form.subtitle" placeholder="一句话描述，如：企业知识库" />
        </el-form-item>
        <el-form-item label="链接地址" required>
          <el-input v-model="form.url" placeholder="https://…" />
        </el-form-item>
        <el-form-item label="所属分区" required>
          <el-radio-group v-model="form.section">
            <el-radio-button value="collab">协作与办公</el-radio-button>
            <el-radio-button value="custom">自定义工具</el-radio-button>
          </el-radio-group>
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
