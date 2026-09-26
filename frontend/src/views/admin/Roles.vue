<script setup lang="ts">
import { nextTick, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from 'lucide-vue-next'
import { roleApi } from '../../api'
import { useUserStore } from '../../stores/user'
import type { PermissionNode, RoleItem } from '../../types'

const store = useUserStore()

const loading = ref(false)
const list = ref<RoleItem[]>([])
const total = ref(0)
const query = reactive({ page: 1, size: 20 })

const dialogVisible = ref(false)
const dialogTitle = ref('新增角色')
const editingId = ref<number | null>(null)
const form = reactive({ name: '', code: '', description: '', status: 1 })

const drawerVisible = ref(false)
const drawerRole = ref<RoleItem | null>(null)
const permissionTree = ref<PermissionNode[]>([])
const treeRef = ref()

const fetchList = () => {
  loading.value = true
  roleApi
    .list(query)
    .then((res) => {
      list.value = res.data.list
      total.value = res.data.total
    })
    .catch(console.error)
    .finally(() => (loading.value = false))
}

const openCreate = () => {
  editingId.value = null
  dialogTitle.value = '新增角色'
  Object.assign(form, { name: '', code: '', description: '', status: 1 })
  dialogVisible.value = true
}

const openEdit = (row: RoleItem) => {
  editingId.value = row.id
  dialogTitle.value = '编辑角色'
  Object.assign(form, { name: row.name, code: row.code, description: row.description, status: row.status })
  dialogVisible.value = true
}

const submit = () => {
  const action = editingId.value
    ? roleApi.update(editingId.value, { name: form.name, description: form.description, status: form.status })
    : roleApi.create({ ...form })
  action
    .then(() => {
      ElMessage.success(editingId.value ? '保存成功' : '新增成功')
      dialogVisible.value = false
      fetchList()
    })
    .catch(console.error)
}

const remove = (row: RoleItem) => {
  ElMessageBox.confirm(`确定删除角色「${row.name}」吗？`, '删除角色', { type: 'warning' })
    .then(() =>
      roleApi.remove(row.id).then(() => {
        ElMessage.success('删除成功')
        fetchList()
      }),
    )
    .catch(() => undefined)
}

/** 打开权限分配抽屉，回显已分配权限（只回填叶子节点，父级由组件联动） */
const openAssign = (row: RoleItem) => {
  drawerRole.value = row
  drawerVisible.value = true
  nextTick(() => {
    const leafIds = collectLeafIds(permissionTree.value).filter((id) => row.permission_ids.includes(id))
    treeRef.value?.setCheckedKeys(leafIds)
  })
}

const collectLeafIds = (nodes: PermissionNode[]): number[] =>
  nodes.flatMap((node) => (node.children && node.children.length ? collectLeafIds(node.children) : [node.id]))

const submitAssign = () => {
  if (!drawerRole.value) return
  const checked = treeRef.value?.getCheckedKeys() ?? []
  const half = treeRef.value?.getHalfCheckedKeys() ?? []
  roleApi
    .assignPermissions(drawerRole.value.id, [...checked, ...half] as number[])
    .then(() => {
      ElMessage.success('权限已更新')
      drawerVisible.value = false
      fetchList()
    })
    .catch(console.error)
}

onMounted(() => {
  fetchList()
  roleApi
    .permissionTree()
    .then((res) => (permissionTree.value = res.data))
    .catch(console.error)
})
</script>

<template>
  <div class="rounded-2xl bg-white p-6 shadow-card">
    <div class="mb-5 flex items-center justify-between">
      <span class="text-sm text-ink-mute">角色用于划分权限边界，内置超级管理员拥有全部权限</span>
      <el-button v-if="store.hasPermission('role:create')" type="primary" @click="openCreate">
        <Plus :size="15" class="mr-1" />新增角色
      </el-button>
    </div>

    <el-table v-loading="loading" :data="list" stripe>
      <el-table-column prop="id" label="ID" width="70" />
      <el-table-column prop="name" label="角色名称" min-width="130" />
      <el-table-column prop="code" label="编码" min-width="130" />
      <el-table-column prop="description" label="描述" min-width="200">
        <template #default="{ row }">{{ row.description || '—' }}</template>
      </el-table-column>
      <el-table-column label="权限数" width="90">
        <template #default="{ row }">
          <el-tag size="small" type="info">{{ row.id === 1 ? '全部' : row.permission_ids.length }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">{{ row.status === 1 ? '启用' : '禁用' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="220" fixed="right">
        <template #default="{ row }">
          <el-button v-if="store.hasPermission('role:assign-permission')" link type="success" @click="openAssign(row)">分配权限</el-button>
          <el-button v-if="store.hasPermission('role:update')" link type="primary" @click="openEdit(row)">编辑</el-button>
          <el-button v-if="store.hasPermission('role:delete') && row.id !== 1" link type="danger" @click="remove(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="mt-5 flex justify-end">
      <el-pagination v-model:current-page="query.page" :page-size="query.size" :total="total" layout="total, prev, pager, next" @change="fetchList" />
    </div>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="460px" destroy-on-close>
      <el-form label-width="90px">
        <el-form-item label="角色名称" required>
          <el-input v-model="form.name" placeholder="如：运营专员" />
        </el-form-item>
        <el-form-item label="角色编码" required>
          <el-input v-model="form.code" :disabled="!!editingId" placeholder="如：operator" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="form.description" type="textarea" :rows="2" placeholder="角色用途说明" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="禁用" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submit">保存</el-button>
      </template>
    </el-dialog>

    <el-drawer v-model="drawerVisible" :title="`分配权限 - ${drawerRole?.name ?? ''}`" size="380px" destroy-on-close>
      <el-tree
        ref="treeRef"
        :data="permissionTree"
        show-checkbox
        node-key="id"
        default-expand-all
        :props="{ label: 'name', children: 'children' }"
      >
        <template #default="{ data }">
          <span class="text-sm">
            {{ data.name }}
            <el-tag size="small" :type="data.type === 'menu' ? 'primary' : 'info'" class="ml-1">{{ data.type === 'menu' ? '菜单' : '接口' }}</el-tag>
          </span>
        </template>
      </el-tree>
      <template #footer>
        <el-button @click="drawerVisible = false">取消</el-button>
        <el-button type="primary" @click="submitAssign">保存</el-button>
      </template>
    </el-drawer>
  </div>
</template>
