<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Copy, Plus } from 'lucide-vue-next'
import { roleApi } from '../../api'
import { useUserStore } from '../../stores/user'
import type { PermissionNode, PermissionPlatform, RoleItem } from '../../types'

const store = useUserStore()

const loading = ref(false)
const list = ref<RoleItem[]>([])
const total = ref(0)
const query = reactive({ page: 1, size: 20 })

const dialogVisible = ref(false)
const dialogTitle = ref('新增角色')
const editingId = ref<number | null>(null)
const form = reactive({ name: '', code: '', description: '', status: 1 })

/** 复制权限：全量角色（含各角色已配置权限），供新增角色与分配权限弹窗共用 */
const copySources = ref<RoleItem[]>([])
/** 新增角色时选择的复制来源，0 表示不复制 */
const copyFromId = ref(0)

/** 分配权限弹窗：平台分组数据、当前页签与勾选状态 */
const assignVisible = ref(false)
const assignRole = ref<RoleItem | null>(null)
/** 分配权限时可选的复制来源（排除当前正在编辑的角色） */
const assignCopySources = computed(() => copySources.value.filter((role) => role.id !== assignRole.value?.id))
const platforms = ref<PermissionPlatform[]>([])
const activePlatform = ref('')
const submittingAssign = ref(false)

/** 勾选状态单源：permissionId -> 是否选中（不依赖树组件，避免页签切换丢失勾选） */
const selected = reactive<Record<number, boolean>>({})

const isSelected = (id: number): boolean => !!selected[id]

const setSelected = (id: number, value: boolean) => {
  if (value) {
    selected[id] = true
  } else {
    delete selected[id]
  }
}

/** 复制源需要全部角色（列表当前只有一页），单独取一次，写操作后随列表一起刷新 */
const fetchCopySources = () => {
  roleApi
    .list({ page: 1, size: 100 })
    .then((res) => (copySources.value = res.data.list))
    .catch(console.error)
}

const fetchList = () => {
  loading.value = true
  roleApi
    .list(query)
    .then((res) => {
      list.value = res.data.list
      total.value = res.data.total
      fetchCopySources()
    })
    .catch(console.error)
    .finally(() => (loading.value = false))
}

const openCreate = () => {
  editingId.value = null
  dialogTitle.value = '新增角色'
  Object.assign(form, { name: '', code: '', description: '', status: 1 })
  copyFromId.value = 0
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
    : roleApi.create({ ...form, copy_from_role_id: copyFromId.value })
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

const menuApiIds = (menu: PermissionNode): number[] => (menu.children ?? []).map((api) => api.id)

/** 菜单全选态：其下接口全部勾选 */
const isMenuChecked = (menu: PermissionNode): boolean => {
  const ids = menuApiIds(menu)
  if (!ids.length) {
    return isSelected(menu.id)
  }
  return ids.every((id) => isSelected(id))
}

/** 菜单半选态：其下接口部分勾选 */
const isMenuIndeterminate = (menu: PermissionNode): boolean => {
  const ids = menuApiIds(menu)
  if (!ids.length) {
    return false
  }
  const hit = ids.filter((id) => isSelected(id)).length
  return hit > 0 && hit < ids.length
}

/** 勾选/取消菜单：联动其下全部接口（无子接口的菜单直接勾选自身） */
const toggleMenu = (menu: PermissionNode, checked: boolean) => {
  const ids = menuApiIds(menu)
  if (!ids.length) {
    setSelected(menu.id, checked)
    return
  }
  ids.forEach((id) => setSelected(id, checked))
}

/** 打开权限分配弹窗，用角色已有权限初始化勾选，并定位到第一个平台页签 */
const openAssign = (row: RoleItem) => {
  assignRole.value = row
  Object.keys(selected).forEach((key) => delete selected[Number(key)])
  row.permission_ids.forEach((id) => (selected[id] = true))
  activePlatform.value = platforms.value[0]?.platform ?? ''
  assignVisible.value = true
}

/** 复制来源角色的权限到当前勾选态（覆盖现有勾选，不立即保存） */
const applyCopy = (sourceId: number | string) => {
  const id = Number(sourceId)
  const source = copySources.value.find((role) => role.id === id)
  if (!source || !assignRole.value) {
    return
  }
  Object.keys(selected).forEach((key) => delete selected[Number(key)])
  source.permission_ids.forEach((permissionId) => setSelected(permissionId, true))

  // 复制后定位到第一个有勾选权限的平台，避免停留在空白页签看不到结果
  const firstHit = platforms.value.find((platform) =>
    platform.menus.some((menu) => isSelected(menu.id) || (menu.children ?? []).some((api) => isSelected(api.id))),
  )
  if (firstHit) {
    activePlatform.value = firstHit.platform
  }

  ElMessage.success(`已复制「${source.name}」的权限，请核对后点击保存`)
}

/** 提交：已勾选的接口 id + 其所属菜单 id（后端按 id 校验后覆盖写入） */
const submitAssign = () => {
  if (!assignRole.value) {
    return
  }
  const ids = new Set<number>()
  platforms.value.forEach((platform) => {
    platform.menus.forEach((menu) => {
      const apiIds = menuApiIds(menu)
      if (!apiIds.length) {
        if (isSelected(menu.id)) {
          ids.add(menu.id)
        }
        return
      }
      let anyChecked = false
      apiIds.forEach((id) => {
        if (isSelected(id)) {
          ids.add(id)
          anyChecked = true
        }
      })
      if (anyChecked) {
        ids.add(menu.id)
      }
    })
  })

  submittingAssign.value = true
  roleApi
    .assignPermissions(assignRole.value.id, [...ids])
    .then(() => {
      ElMessage.success('权限已更新')
      assignVisible.value = false
      fetchList()
    })
    .catch(console.error)
    .finally(() => (submittingAssign.value = false))
}

onMounted(() => {
  fetchList()
  roleApi
    .permissionTree()
    .then((res) => (platforms.value = res.data))
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
        <el-form-item v-if="!editingId" label="复制权限">
          <el-select v-model="copyFromId" class="w-full" placeholder="不复制，创建后再分配">
            <el-option label="不复制，创建后再分配" :value="0" />
            <el-option
              v-for="role in copySources"
              :key="role.id"
              :label="`${role.name}（${role.id === 1 ? '全部' : role.permission_ids.length} 项权限）`"
              :value="role.id"
            />
          </el-select>
          <div class="w-full text-xs leading-5 text-ink-mute">选择已有角色，新建时自动复制其已配置的权限</div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="submit">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="assignVisible" width="40%" align-center destroy-on-close>
      <template #header>
        <div class="flex w-full items-center justify-between gap-3 pr-10">
          <span class="truncate text-base font-medium text-ink">分配权限 - {{ assignRole?.name ?? '' }}</span>
          <el-dropdown trigger="click" @command="applyCopy">
            <el-button size="small">
              <Copy :size="14" class="mr-1" />复制权限
            </el-button>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item v-for="role in assignCopySources" :key="role.id" :command="role.id">
                  {{ role.name }}（{{ role.id === 1 ? '全部' : role.permission_ids.length }} 项）
                </el-dropdown-item>
                <el-dropdown-item v-if="!assignCopySources.length" disabled>暂无可复制的角色</el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </template>
      <el-tabs v-if="platforms.length" v-model="activePlatform">
        <el-tab-pane v-for="platform in platforms" :key="platform.platform" :label="platform.name" :name="platform.platform">
          <!-- 固定高度：切换平台页签时弹窗高度保持一致，内容超高则内部滚动 -->
          <div class="h-[52vh] space-y-3 overflow-y-auto pr-1">
            <div v-for="menu in platform.menus" :key="menu.id" class="rounded-xl border border-[#EEF1F8] bg-white p-3">
              <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 items-center gap-2">
                  <span class="truncate text-sm font-medium text-ink">{{ menu.name }}</span>
                  <el-tag size="small" type="primary">菜单</el-tag>
                </div>
                <el-checkbox
                  :model-value="isMenuChecked(menu)"
                  :indeterminate="isMenuIndeterminate(menu)"
                  @change="(val: boolean) => toggleMenu(menu, val)"
                >
                  全选
                </el-checkbox>
              </div>

              <div v-if="menu.children?.length" class="mt-2 grid grid-cols-2 gap-1.5">
                <label
                  v-for="api in menu.children"
                  :key="api.id"
                  class="flex cursor-pointer items-start gap-2 rounded-lg px-2 py-1.5 transition-colors hover:bg-[#F5F7FA]"
                >
                  <el-checkbox :model-value="isSelected(api.id)" @change="(val: boolean) => setSelected(api.id, val)" />
                  <span class="min-w-0">
                    <span class="block text-sm text-ink">{{ api.name }}</span>
                    <span class="block truncate text-xs text-ink-mute">{{ api.path }}</span>
                  </span>
                </label>
              </div>
            </div>
          </div>
        </el-tab-pane>
      </el-tabs>
      <div v-else class="flex h-[52vh] items-center justify-center text-sm text-ink-mute">暂无可分配的权限</div>

      <template #footer>
        <el-button @click="assignVisible = false">取消</el-button>
        <el-button type="primary" :loading="submittingAssign" @click="submitAssign">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>
