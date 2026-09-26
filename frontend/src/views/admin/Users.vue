<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search } from 'lucide-vue-next'
import { roleApi, userApi } from '../../api'
import { useUserStore } from '../../stores/user'
import type { RoleItem, UserItem } from '../../types'

const store = useUserStore()

const loading = ref(false)
const list = ref<UserItem[]>([])
const total = ref(0)
const query = reactive({ page: 1, size: 10, keyword: '' })

const roleOptions = ref<RoleItem[]>([])

const dialogVisible = ref(false)
const dialogTitle = ref('新增用户')
const editingId = ref<number | null>(null)
const form = reactive({ username: '', password: '', nickname: '', status: 1, role_ids: [] as number[] })

const fetchList = () => {
  loading.value = true
  userApi
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
  dialogTitle.value = '新增用户'
  Object.assign(form, { username: '', password: '', nickname: '', status: 1, role_ids: [] })
  dialogVisible.value = true
}

const openEdit = (row: UserItem) => {
  editingId.value = row.id
  dialogTitle.value = '编辑用户'
  Object.assign(form, {
    username: row.username,
    password: '',
    nickname: row.nickname,
    status: row.status,
    role_ids: row.roles.map((r) => r.id),
  })
  dialogVisible.value = true
}

const submit = () => {
  const payload: Record<string, unknown> = {
    nickname: form.nickname,
    status: form.status,
    role_ids: form.role_ids,
  }
  if (form.password) payload.password = form.password

  const action = editingId.value
    ? userApi.update(editingId.value, payload)
    : userApi.create({ ...payload, username: form.username, password: form.password })

  action
    .then(() => {
      ElMessage.success(editingId.value ? '保存成功' : '新增成功')
      dialogVisible.value = false
      fetchList()
    })
    .catch(console.error)
}

const toggleStatus = (row: UserItem) => {
  userApi
    .update(row.id, { status: row.status })
    .then(() => ElMessage.success('状态已更新'))
    .catch((err) => {
      console.error(err)
      row.status = row.status === 1 ? 0 : 1
    })
}

const resetPassword = (row: UserItem) => {
  ElMessageBox.confirm(`确定重置「${row.nickname}」的密码吗？将随机生成新密码。`, '重置密码', { type: 'warning' })
    .then(() =>
      userApi.resetPassword(row.id).then((res) => {
        ElMessageBox.alert(`新密码：${res.data.password}，请复制后妥善告知用户。`, '重置成功', {
          confirmButtonText: '知道了',
        })
      }),
    )
    .catch(() => undefined)
}

const remove = (row: UserItem) => {
  ElMessageBox.confirm(`确定删除用户「${row.nickname}」吗？`, '删除用户', { type: 'warning' })
    .then(() =>
      userApi.remove(row.id).then(() => {
        ElMessage.success('删除成功')
        fetchList()
      }),
    )
    .catch(() => undefined)
}

onMounted(() => {
  fetchList()
  roleApi
    .list({ page: 1, size: 100 })
    .then((res) => (roleOptions.value = res.data.list.filter((r) => r.status === 1)))
    .catch(console.error)
})
</script>

<template>
  <div class="rounded-2xl bg-white p-6 shadow-card">
    <div class="mb-5 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <el-input v-model="query.keyword" placeholder="搜索账号 / 昵称" clearable class="!w-64" @keyup.enter="fetchList">
          <template #prefix><Search :size="15" /></template>
        </el-input>
        <el-button type="primary" plain @click="fetchList">查询</el-button>
      </div>
      <el-button v-if="store.hasPermission('user:create')" type="primary" @click="openCreate">
        <Plus :size="15" class="mr-1" />新增用户
      </el-button>
    </div>

    <el-table v-loading="loading" :data="list" stripe>
      <el-table-column prop="id" label="ID" width="70" />
      <el-table-column prop="username" label="账号" min-width="120" />
      <el-table-column prop="nickname" label="昵称" min-width="120" />
      <el-table-column label="角色" min-width="150">
        <template #default="{ row }">
          <el-tag v-for="role in row.roles" :key="role.id" size="small" class="mr-1">{{ role.name }}</el-tag>
          <span v-if="!row.roles.length" class="text-ink-mute">—</span>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-switch v-model="row.status" :active-value="1" :inactive-value="0" @change="toggleStatus(row)" />
        </template>
      </el-table-column>
      <el-table-column prop="last_login_at" label="最后登录" min-width="150">
        <template #default="{ row }">{{ row.last_login_at || '从未登录' }}</template>
      </el-table-column>
      <el-table-column label="操作" width="230" fixed="right">
        <template #default="{ row }">
          <el-button v-if="store.hasPermission('user:update')" link type="primary" @click="openEdit(row)">编辑</el-button>
          <el-button v-if="store.hasPermission('user:reset-password')" link type="warning" @click="resetPassword(row)">重置密码</el-button>
          <el-button v-if="store.hasPermission('user:delete')" link type="danger" @click="remove(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="mt-5 flex justify-end">
      <el-pagination
        v-model:current-page="query.page"
        v-model:page-size="query.size"
        :total="total"
        :page-sizes="[10, 20, 50]"
        layout="total, sizes, prev, pager, next"
        @change="fetchList"
      />
    </div>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="480px" destroy-on-close>
      <el-form label-width="90px">
        <el-form-item label="账号" required>
          <el-input v-model="form.username" :disabled="!!editingId" placeholder="3-20位字母、数字或下划线" />
        </el-form-item>
        <el-form-item :label="editingId ? '重置密码' : '密码'" :required="!editingId">
          <el-input v-model="form.password" type="password" show-password :placeholder="editingId ? '留空则不修改' : '至少8位'" />
        </el-form-item>
        <el-form-item label="昵称" required>
          <el-input v-model="form.nickname" placeholder="用户显示名称" />
        </el-form-item>
        <el-form-item label="角色">
          <el-select v-model="form.role_ids" multiple class="w-full" placeholder="选择角色">
            <el-option v-for="role in roleOptions" :key="role.id" :label="role.name" :value="role.id" />
          </el-select>
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
  </div>
</template>
