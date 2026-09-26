<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { RotateCw, Search, Sparkles, Upload } from 'lucide-vue-next'
import SkillCard from '../../components/SkillCard.vue'
import SkillCategoryNav from '../../components/SkillCategoryNav.vue'
import SkillDetailDrawer from '../../components/SkillDetailDrawer.vue'
import { useSkillCategories } from '../../composables/useSkillCategories'
import { skillApi } from '../../api'
import type { SkillItem, SkillQuery } from '../../types'

const router = useRouter()
const { functionCategories, typeCategories, loading: categoryLoading, load: loadCategories, functionName, typeName } =
  useSkillCategories()

const loading = ref(false)
const errorMsg = ref('')
const list = ref<SkillItem[]>([])
const total = ref(0)

/** 各职能分类下的已上架数量，用于分类导航计数 */
const counts = ref<Record<number, number>>({})
const publishedTotal = ref(0)

const query = reactive<SkillQuery>({
  page: 1,
  size: 12,
  keyword: '',
  function_category_id: '',
  type_category_id: '',
  status: 1,
  sort: 'latest',
})

const fetchList = () => {
  loading.value = true
  errorMsg.value = ''
  skillApi
    .list({ ...query, status: 1 })
    .then((res) => {
      list.value = res.data.list
      total.value = res.data.total
    })
    .catch(() => {
      errorMsg.value = 'Skill 列表加载失败，请检查网络后重试'
    })
    .finally(() => (loading.value = false))
}

/** 计数单独拉一次大页，避免翻页时导航计数跳动 */
const fetchCounts = () => {
  skillApi
    .list({ page: 1, size: 500, status: 1 })
    .then((res) => {
      const map: Record<number, number> = {}
      res.data.list.forEach((item) => {
        map[item.function_category_id] = (map[item.function_category_id] ?? 0) + 1
      })
      counts.value = map
      publishedTotal.value = res.data.total
    })
    .catch(console.error)
}

const switchCategory = (id: number | '') => {
  query.function_category_id = id
  query.page = 1
  fetchList()
}

const resetPage = () => {
  query.page = 1
  fetchList()
}

onMounted(() => {
  loadCategories()
  fetchCounts()
  fetchList()
})

// ---------- 详情抽屉 ----------

const drawerVisible = ref(false)
const current = ref<SkillItem | null>(null)

const openDetail = (skill: SkillItem) => {
  current.value = skill
  drawerVisible.value = true
}

const currentFunctionName = computed(() => (current.value ? functionName(current.value.function_category_id) : ''))
const currentTypeName = computed(() => (current.value ? typeName(current.value.type_category_id) : ''))
</script>

<template>
  <div class="mx-auto w-full max-w-[1920px] px-8 pb-16">
    <!-- 头部 -->
    <section class="relative mt-8 overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-[#6D7CF5] to-primary-purple px-9 py-8 shadow-card-hover">
      <div class="pointer-events-none absolute -right-16 -top-24 h-72 w-72 rounded-full bg-white/15 blur-2xl" aria-hidden="true" />
      <div class="relative">
        <h2 class="flex items-center gap-2 text-[28px] font-semibold text-white">
          <Sparkles :size="26" />AI Skill 广场
        </h2>
        <p class="mt-2 text-[14px] text-white/85">
          发现企业内部沉淀的 AI 能力：按职能与类型快速定位，查看提示词正文与运行参数
        </p>

        <div class="mt-6 flex flex-wrap items-center gap-3">
          <div class="flex h-11 w-[420px] items-center gap-2 rounded-xl bg-white px-4 shadow-md">
            <Search :size="17" class="text-ink-mute" />
            <input
              v-model="query.keyword"
              type="text"
              placeholder="搜索 Skill 名称、描述或作者…"
              class="h-full w-full border-0 bg-transparent text-[14px] text-ink outline-none placeholder:text-ink-mute"
              @keyup.enter="resetPage"
            />
          </div>
          <button
            type="button"
            class="flex h-11 cursor-pointer items-center gap-2 rounded-xl border border-white/50 bg-white/15 px-5 text-[14px] font-medium text-white backdrop-blur transition-colors duration-200 hover:bg-white/25"
            @click="router.push('/skills/my')"
          >
            <Upload :size="16" />我的 Skill
          </button>
        </div>
      </div>
    </section>

    <!-- 分类导航 -->
    <section class="mt-7">
      <SkillCategoryNav
        :categories="functionCategories"
        :model-value="query.function_category_id ?? ''"
        :counts="counts"
        :total="publishedTotal"
        @update:model-value="switchCategory"
      />
    </section>

    <!-- 筛选条 -->
    <section class="mt-5 flex flex-wrap items-center justify-between gap-3">
      <p class="text-[13px] text-ink-mute">
        共 <b class="tabular font-semibold text-primary-deep">{{ total }}</b> 个已上架 Skill
      </p>
      <div class="flex items-center gap-3">
        <el-select
          v-model="query.type_category_id"
          placeholder="全部类型"
          clearable
          class="!w-40"
          @change="resetPage"
        >
          <el-option v-for="c in typeCategories" :key="c.id" :label="c.name" :value="c.id" />
        </el-select>
        <el-select v-model="query.sort" class="!w-36" @change="resetPage">
          <el-option label="最多点赞" value="likes" />
          <el-option label="最近更新" value="latest" />
          <el-option label="名称排序" value="name" />
        </el-select>
      </div>
    </section>

    <!-- 加载失败 -->
    <section
      v-if="errorMsg"
      class="mt-8 flex flex-col items-center rounded-2xl border border-dashed border-[#F3C6C6] bg-white py-14"
    >
      <p class="text-[15px] text-[#F5222D]">{{ errorMsg }}</p>
      <el-button class="mt-4" type="primary" plain @click="fetchList">
        <RotateCw :size="14" class="mr-1" />重新加载
      </el-button>
    </section>

    <!-- 空态 -->
    <section
      v-else-if="!loading && !list.length"
      class="mt-8 flex flex-col items-center rounded-2xl border border-dashed border-[#C9D4EE] bg-white py-16"
    >
      <Sparkles :size="40" class="text-ink-mute" />
      <p class="mt-4 text-[15px] text-ink-sub">当前筛选条件下还没有 Skill</p>
      <p class="mt-1 text-[13px] text-ink-mute">换个分类或关键词试试，也可以上传你的第一个 Skill</p>
      <el-button class="mt-5" type="primary" @click="router.push('/skills/my')">去上传 Skill</el-button>
    </section>

    <!-- 卡片网格 -->
    <section v-else v-loading="loading || categoryLoading" class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
      <div v-for="skill in list" :key="skill.id" class="min-h-[196px]">
        <SkillCard
          :skill="skill"
          :function-name="functionName(skill.function_category_id)"
          :type-name="typeName(skill.type_category_id)"
          @detail="openDetail"
        />
      </div>
    </section>

    <div v-if="total > query.size" class="mt-8 flex justify-end">
      <el-pagination
        v-model:current-page="query.page"
        v-model:page-size="query.size"
        :total="total"
        layout="total, sizes, prev, pager, next"
        :page-sizes="[8, 12, 24]"
        @size-change="fetchList"
        @current-change="fetchList"
      />
    </div>

    <SkillDetailDrawer
      v-model="drawerVisible"
      :skill="current"
      :function-name="currentFunctionName"
      :type-name="currentTypeName"
    />
  </div>
</template>
