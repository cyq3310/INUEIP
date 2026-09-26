<script setup lang="ts">
import { onBeforeUnmount, onMounted, reactive, ref, shallowRef } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Search } from 'lucide-vue-next'
import '@wangeditor/editor/dist/css/style.css'
import { Editor, Toolbar } from '@wangeditor/editor-for-vue'
import { announcementApi } from '../../api'
import { useUserStore } from '../../stores/user'
import type { AnnouncementItem } from '../../types'

const store = useUserStore()

const loading = ref(false)
const list = ref<AnnouncementItem[]>([])
const total = ref(0)
const query = reactive({ page: 1, size: 15, keyword: '', status: '' })

const dialogVisible = ref(false)
const dialogTitle = ref('新增公告')
const editingId = ref<number | null>(null)
const form = reactive({ title: '', summary: '' })
const contentHtml = ref('')

const editorRef = shallowRef()
const toolbarConfig = {}
const editorConfig = {
  placeholder: '请输入公告正文…',
  MENU_CONF: {
    uploadImage: {
      // 与后端校验保持一致：jpg/jpeg/png/webp/gif，单张 ≤5MB
      allowedFileTypes: ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'],
      maxFileSize: 5 * 1024 * 1024,
      // 工具栏/拖拽/粘贴截图都走这里；公告未保存时先存临时目录，保存后由后端迁移到公告ID目录
      customUpload: async (file: File, insertFn: (url: string, alt: string, href: string) => void) => {
        try {
          const res = await announcementApi.upload(editingId.value ?? 0, file)
          insertFn(res.data.url, file.name, '')
        } catch {
          ElMessage.error('图片上传失败')
        }
      },
    },
  },
}
const handleCreated = (editor: object) => {
  editorRef.value = editor
}
onBeforeUnmount(() => {
  editorRef.value?.destroy()
})

const statusMeta: Record<number, { label: string; type: 'info' | 'success' | 'warning' }> = {
  0: { label: '草稿', type: 'info' },
  1: { label: '已发布', type: 'success' },
  2: { label: '已下架', type: 'warning' },
}

const fetchList = () => {
  loading.value = true
  announcementApi
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
  dialogTitle.value = '新增公告'
  Object.assign(form, { title: '', summary: '' })
  contentHtml.value = ''
  dialogVisible.value = true
}

const openEdit = (row: AnnouncementItem) => {
  editingId.value = row.id
  dialogTitle.value = '编辑公告'
  Object.assign(form, { title: row.title, summary: row.summary })
  contentHtml.value = row.content ?? ''
  dialogVisible.value = true
}

/** status: 0 存草稿，1 直接发布 */
const submit = (status: 0 | 1) => {
  if (!form.title.trim()) {
    ElMessage.warning('请输入公告标题')
    return
  }
  if ([...form.title.trim()].length > 32) {
    ElMessage.warning('标题最多32个字')
    return
  }
  const payload = { title: form.title, summary: form.summary, content: contentHtml.value, status }
  const action = editingId.value
    ? announcementApi.update(editingId.value, payload)
    : announcementApi.create(payload)
  action
    .then(() => {
      ElMessage.success(status === 1 ? '已发布' : '草稿已保存')
      dialogVisible.value = false
      fetchList()
    })
    .catch(console.error)
}

const publish = (row: AnnouncementItem) => {
  announcementApi
    .publish(row.id)
    .then(() => {
      ElMessage.success('已发布')
      fetchList()
    })
    .catch(console.error)
}

const offline = (row: AnnouncementItem) => {
  ElMessageBox.confirm(`确定下架「${row.title}」吗？下架后门户不再显示。`, '下架公告', { type: 'warning' })
    .then(() =>
      announcementApi.offline(row.id).then(() => {
        ElMessage.success('已下架')
        fetchList()
      }),
    )
    .catch(() => undefined)
}

const toggleTop = (row: AnnouncementItem, isTop: 0 | 1) => {
  announcementApi
    .top(row.id, isTop)
    .then(() => {
      ElMessage.success(isTop === 1 ? '已置顶' : '已取消置顶')
      fetchList()
    })
    .catch(() => undefined)
}

const remove = (row: AnnouncementItem) => {
  ElMessageBox.confirm(`确定删除公告「${row.title}」吗？`, '删除公告', { type: 'warning' })
    .then(() =>
      announcementApi.remove(row.id).then(() => {
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
    <el-alert
      type="info"
      :closable="false"
      show-icon
      class="!mb-5"
      title="公告发布规范"
      description="1. 标题不超过 32 个字；2. 正文单张图片不超过 5MB（支持 jpg/png/webp/gif）；3. 最多同时置顶 3 条公告（仅已发布的公告可置顶，下架后自动释放名额）；"
    />

    <div class="mb-5 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <el-input v-model="query.keyword" placeholder="搜索公告标题" clearable class="!w-56" @keyup.enter="fetchList">
          <template #prefix><Search :size="15" /></template>
        </el-input>
        <el-select v-model="query.status" placeholder="全部状态" clearable class="!w-32" @change="fetchList">
          <el-option label="草稿" :value="0" />
          <el-option label="已发布" :value="1" />
          <el-option label="已下架" :value="2" />
        </el-select>
        <el-button type="primary" plain @click="fetchList">查询</el-button>
      </div>
      <el-button v-if="store.hasPermission('announcement:create')" type="primary" @click="openCreate">
        <Plus :size="15" class="mr-1" />发布公告
      </el-button>
    </div>

    <el-table v-loading="loading" :data="list" stripe>
      <el-table-column prop="id" label="ID" width="70" />
      <el-table-column prop="title" label="标题" min-width="200" show-overflow-tooltip />
      <el-table-column prop="summary" label="摘要" min-width="240" show-overflow-tooltip />
      <el-table-column label="状态" width="100">
        <template #default="{ row }">
          <el-tag size="small" :type="statusMeta[row.status]?.type ?? 'info'">{{ statusMeta[row.status]?.label ?? row.status }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="published_at" label="发布时间" min-width="160">
        <template #default="{ row }">{{ row.published_at || '—' }}</template>
      </el-table-column>
      <el-table-column label="置顶" width="80">
        <template #default="{ row }">
          <el-tag v-if="row.is_top === 1" size="small" type="danger">置顶</el-tag>
          <span v-else class="text-ink-mute">—</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="270" fixed="right">
        <template #default="{ row }">
          <el-button v-if="store.hasPermission('announcement:update')" link type="primary" @click="openEdit(row)">编辑</el-button>
          <el-button v-if="store.hasPermission('announcement:publish') && row.status !== 1" link type="success" @click="publish(row)">发布</el-button>
          <el-button v-if="store.hasPermission('announcement:publish') && row.status === 1" link type="warning" @click="offline(row)">下架</el-button>
          <el-button
            v-if="store.hasPermission('announcement:top') && row.status === 1 && row.is_top !== 1"
            link
            type="warning"
            @click="toggleTop(row, 1)"
          >
            置顶
          </el-button>
          <el-button v-else-if="store.hasPermission('announcement:top') && row.is_top === 1" link type="info" @click="toggleTop(row, 0)">
            取消置顶
          </el-button>
          <el-button v-if="store.hasPermission('announcement:delete')" link type="danger" @click="remove(row)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <div class="mt-5 flex justify-end">
      <el-pagination v-model:current-page="query.page" :page-size="query.size" :total="total" layout="total, prev, pager, next" @change="fetchList" />
    </div>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" class="ann-edit-dialog" width="min(1500px, 92vw)" destroy-on-close top="4vh">
      <el-form label-width="90px">
        <el-form-item label="标题" required>
          <el-input v-model="form.title" maxlength="32" show-word-limit placeholder="公告标题（最多32个字）" />
        </el-form-item>
        <el-form-item label="摘要">
          <el-input v-model="form.summary" type="textarea" :rows="2" maxlength="500" placeholder="列表页展示的副标题/摘要" />
        </el-form-item>
        <el-form-item label="正文" class="ann-editor-item">
          <div class="w-full rounded-lg border border-[#E3E9F5]">
            <Toolbar :editor="editorRef" :defaultConfig="toolbarConfig" mode="default" class="border-b border-[#E3E9F5]" />
            <Editor v-model="contentHtml" :defaultConfig="editorConfig" mode="default" class="!h-[560px]" @onCreated="handleCreated" />
          </div>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button @click="submit(0)">存为草稿</el-button>
        <el-button type="primary" @click="submit(1)">立即发布</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
/* 正文编辑区固定高度（560px），内容超出时在编辑器内部滚动；
   编辑项底部留 10px 间距，使编辑框底部位于「立即发布」按钮上方 */
:deep(.ann-edit-dialog .ann-editor-item) {
  margin-bottom: 10px;
}
</style>
