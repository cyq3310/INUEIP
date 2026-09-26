<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Heart, Plus, RotateCw, Search } from 'lucide-vue-next'
import LucideIcon from '../../components/LucideIcon.vue'
import SkillDetailDrawer from '../../components/SkillDetailDrawer.vue'
import SkillFormDialog from '../../components/SkillFormDialog.vue'
import { useSkillCategories } from '../../composables/useSkillCategories'
import { skillSourceMeta, skillStatusMeta } from '../../config/skill-meta'
import { skillApi } from '../../api'
import { relativeTime } from '../../utils/time'
import type { SkillItem, SkillQuery, SkillStats } from '../../types'

const {
  functionCategories,
  typeCategories,
  loading: categoryLoading,
  load: loadCategories,
  functionName,
  typeName,
} = useSkillCategories()

const TABS = [
  { label: '全部', value: '' },
  { label: '草稿', value: '0' },
  { label: '已上架', value: '1' },
  { label: '已下架', value: '2' },
]

const loading = ref(false)
const errorMsg = ref('')
const list = ref<SkillItem[]>([])
const total = ref(0)
const stats = ref<SkillStats>({ total: 0, published: 0, draft: 0, offline: 0 })
/** 已上架 Skill 覆盖到的职能分类数 */
const coveredFunctions = ref(0)

const statusTab = ref('')
const query = reactive<SkillQuery>({ page: 1, size: 10, keyword: '', function_category_id: '', type_category_id: '' })

const fetchList = () => {
  loading.value = true
  errorMsg.value = ''
  skillApi
    .list({
      ...query,
      status: statusTab.value === '' ? '' : (Number(statusTab.value) as 0 | 1 | 2),
    })
    .then((res) => {
      list.value = res.data.list
      total.value = res.data.total
    })
    .catch(() => {
      errorMsg.value = 'Skill 列表加载失败，请检查网络后重试'
    })
    .finally(() => (loading.value = false))
}

const fetchStats = () => {
  skillApi
    .stats()
    .then((res) => (stats.value = res.data))
    .catch(console.error)
}

/** 覆盖职能数单独统计，不受当前 Tab 与筛选影响 */
const fetchCoverage = () => {
  skillApi
    .list({ page: 1, size: 500, status: 1 })
    .then((res) => {
      coveredFunctions.value = new Set(res.data.list.map((s) => s.function_category_id)).size
    })
    .catch(console.error)
}

const resetPage = () => {
  query.page = 1
  fetchList()
}

const refreshAll = () => {
  fetchList()
  fetchStats()
  fetchCoverage()
}

onMounted(() => {
  loadCategories()
  fetchStats()
  fetchCoverage()
  fetchList()
})

// ---------- 新建 / 编辑 ----------

const dialogVisible = ref(false)
const editing = ref<SkillItem | null>(null)

const openCreate = () => {
  editing.value = null
  dialogVisible.value = true
}

const openEdit = (row: SkillItem) => {
  editing.value = row
  dialogVisible.value = true
}

// ---------- 上下架 / 删除 ----------

const toggleStatus = (row: SkillItem) => {
  const next = row.status === 1 ? 2 : 1
  skillApi
    .setStatus(row.id, next)
    .then(() => {
      ElMessage.success(next === 1 ? '已上架，全员可在广场看到' : '已下架，广场不再展示')
      refreshAll()
    })
    .catch((err: Error) => ElMessage.error(err.message || '操作失败，请稍后重试'))
}

const remove = (row: SkillItem) => {
  ElMessageBox.confirm(
    `确定删除「${row.owner_name}」上传的 Skill「${row.name}」吗？删除后不可恢复。`,
    '删除 Skill',
    { type: 'warning' },
  )
    .then(() =>
      skillApi.remove(row.id).then(() => {
        ElMessage.success('删除成功')
        refreshAll()
      }),
    )
    .catch(() => undefined)
}

// ---------- 详情抽屉 ----------

const drawerVisible = ref(false)
const current = ref<SkillItem | null>(null)
const openDetail = (row: SkillItem) => {
  current.value = row
  drawerVisible.value = true
}

const currentFunctionName = computed(() => (current.value ? functionName(current.value.function_category_id) : ''))
const currentTypeName = computed(() => (current.value ? typeName(current.value.type_category_id) : ''))

const statCards = computed(() => [
  { label: 'Skill 总数', value: stats.value.total, dot: 'bg-primary', tone: 'text-primary' },
  { label: '已上架', value: stats.value.published, dot: 'bg-[#19BE6B]', tone: 'text-[#19BE6B]' },
  { label: '草稿待处理', value: stats.value.draft, dot: 'bg-[#FF9F1C]', tone: 'text-[#FF9F1C]' },
  { label: '覆盖职能', value: coveredFunctions.value, dot: 'bg-primary-purple', tone: 'text-primary-purple' },
])
</script>

<template>
  <div>
    <!-- 统计 -->
    <section class="grid grid-cols-2 gap-4 xl:grid-cols-4">
      <div
        v-for="card in statCards"
        :key="card.label"
        class="rounded-2xl bg-white px-5 py-4 shadow-card transition-shadow duration-200 hover:shadow-card-hover"
      >
        <div class="flex items-center gap-2 text-[13px] text-ink-sub">
          <span class="h-2 w-2 rounded-full" :class="card.dot" />{{ card.label }}
        </div>
        <div class="tabular mt-2 text-[26px] font-semibold leading-none" :class="card.tone">{{ card.value }}</div>
      </div>
    </section>

    <section class="mt-5 rounded-2xl bg-white p-6 shadow-card">
      <div class="mb-5 flex items-center justify-between">
        <el-tabs v-model="statusTab" class="section-tabs" @tab-change="resetPage">
          <el-tab-pane v-for="tab in TABS" :key="tab.value" :label="tab.label" :name="tab.value" />
        </el-tabs>
        <el-button type="primary" @click="openCreate">
          <Plus :size="15" class="mr-1" />新建 Skill
        </el-button>
      </div>

      <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="flex h-9 w-72 items-center gap-2 rounded-lg bg-[#F7F9FE] px-3">
          <Search :size="15" class="text-ink-mute" />
          <input
            v-model="query.keyword"
            type="text"
            placeholder="搜索名称 / 描述 / 作者"
            class="h-full w-full border-0 bg-transparent text-[13px] text-ink outline-none placeholder:text-ink-mute"
            @keyup.enter="resetPage"
          />
        </div>
        <el-select
          v-model="query.function_category_id"
          placeholder="全部职能"
          clearable
          class="!w-36"
          @change="resetPage"
        >
          <el-option v-for="c in functionCategories" :key="c.id" :label="c.name" :value="c.id" />
        </el-select>
        <el-select v-model="query.type_category_id" placeholder="全部类型" clearable class="!w-40" @change="resetPage">
          <el-option v-for="c in typeCategories" :key="c.id" :label="c.name" :value="c.id" />
        </el-select>
        <el-button type="primary" plain @click="resetPage">查询</el-button>
      </div>

      <div v-if="errorMsg" class="flex flex-col items-center py-14">
        <p class="text-[15px] text-[#F5222D]">{{ errorMsg }}</p>
        <el-button class="mt-4" type="primary" plain @click="fetchList">
          <RotateCw :size="14" class="mr-1" />重新加载
        </el-button>
      </div>

      <el-table v-else v-loading="loading || categoryLoading" :data="list" stripe>
        <el-table-column label="Skill" min-width="240">
          <template #default="{ row }">
            <div class="flex items-center gap-3">
              <div
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-primary to-primary-purple text-white"
              >
                <LucideIcon :name="row.icon" :size="17" />
              </div>
              <div class="min-w-0">
                <button
                  type="button"
                  class="cursor-pointer truncate text-[14px] font-medium text-ink transition-colors hover:text-primary-deep"
                  @click="openDetail(row)"
                >
                  {{ row.name }}
                </button>
                <p class="truncate text-xs text-ink-mute">{{ row.summary }}</p>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="职能" width="90">
          <template #default="{ row }">{{ functionName(row.function_category_id) }}</template>
        </el-table-column>
        <el-table-column label="类型" width="110">
          <template #default="{ row }">{{ typeName(row.type_category_id) }}</template>
        </el-table-column>
        <el-table-column label="来源" width="96">
          <template #default="{ row }">
            <span class="rounded-md px-2 py-0.5 text-xs" :class="skillSourceMeta(row.source).cls">
              {{ skillSourceMeta(row.source).label }}
            </span>
          </template>
        </el-table-column>
        <el-table-column prop="owner_name" label="作者" width="90" />
        <el-table-column label="点赞" width="88" align="center">
          <template #default="{ row }">
            <span
              class="tabular inline-flex items-center gap-1 text-[13px]"
              :class="row.like_count > 0 ? 'text-[#F5222D]' : 'text-ink-mute'"
            >
              <Heart :size="13" :fill="row.like_count > 0 ? 'currentColor' : 'none'" />
              {{ row.like_count }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <span class="flex items-center gap-1.5 text-[13px]" :class="skillStatusMeta(row.status).text">
              <span class="h-2 w-2 rounded-full" :class="skillStatusMeta(row.status).dot" />
              {{ skillStatusMeta(row.status).label }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="更新时间" width="120">
          <template #default="{ row }">
            <span class="tabular text-[13px] text-ink-sub">{{ relativeTime(row.updated_at) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="160" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click="openEdit(row)">编辑</el-button>
            <el-button link type="primary" @click="toggleStatus(row)">
              {{ row.status === 1 ? '下架' : '上架' }}
            </el-button>
            <el-button link type="danger" @click="remove(row)">删除</el-button>
          </template>
        </el-table-column>

        <template #empty>
          <div class="flex flex-col items-center py-14">
            <Search :size="34" class="text-ink-mute" />
            <p class="mt-3 text-[15px] text-ink-sub">当前筛选条件下没有 Skill</p>
            <p class="mt-1 text-[13px] text-ink-mute">可调整状态或分类后重新查询</p>
          </div>
        </template>
      </el-table>

      <div class="mt-5 flex justify-end">
        <el-pagination
          v-model:current-page="query.page"
          :page-size="query.size"
          :total="total"
          layout="total, prev, pager, next"
          @current-change="fetchList"
        />
      </div>
    </section>

    <SkillFormDialog
      v-model="dialogVisible"
      :skill="editing"
      :function-categories="functionCategories"
      :type-categories="typeCategories"
      @saved="refreshAll"
    />

    <SkillDetailDrawer
      v-model="drawerVisible"
      :skill="current"
      :function-name="currentFunctionName"
      :type-name="currentTypeName"
    />
  </div>
</template>

<style scoped>
.section-tabs :deep(.el-tabs__header) {
  margin-bottom: 0;
}
</style>
