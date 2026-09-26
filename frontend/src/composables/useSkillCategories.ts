import { computed, ref } from 'vue'
import { skillCategoryApi } from '../api'
import type { SkillCategory } from '../types'

/**
 * 加载并缓存「职能 / 类型」两套分类，供广场、我的 Skill 与后台页面复用。
 */
export function useSkillCategories() {
  const functionCategories = ref<SkillCategory[]>([])
  const typeCategories = ref<SkillCategory[]>([])
  const loading = ref(false)

  const load = () => {
    loading.value = true
    return Promise.all([skillCategoryApi.list('function'), skillCategoryApi.list('type')])
      .then(([fn, ty]) => {
        functionCategories.value = fn.data.filter((c) => c.status === 1)
        typeCategories.value = ty.data.filter((c) => c.status === 1)
      })
      .catch(console.error)
      .finally(() => (loading.value = false))
  }

  const nameOf = (list: SkillCategory[], id: number) =>
    list.find((c) => c.id === id)?.name ?? '未分类'

  const functionName = (id: number) => nameOf(functionCategories.value, id)
  const typeName = (id: number) => nameOf(typeCategories.value, id)

  /** id -> 分类项，表格中展示图标时用 */
  const functionMap = computed(() => new Map(functionCategories.value.map((c) => [c.id, c])))
  const typeMap = computed(() => new Map(typeCategories.value.map((c) => [c.id, c])))

  return { functionCategories, typeCategories, functionMap, typeMap, loading, load, functionName, typeName }
}
