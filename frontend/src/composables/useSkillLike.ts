import { ref } from 'vue'
import { ElMessage } from 'element-plus'
import { skillApi } from '../api'
import type { SkillItem } from '../types'

/**
 * 点赞 / 取消点赞：就地更新列表项，避免整表刷新导致滚动位置跳动。
 * 采用乐观更新，请求失败时回滚并以服务端返回的计数为准。
 */
export function useSkillLike() {
  /** 正在提交中的 Skill id，防止连点产生重复请求 */
  const pending = ref<number[]>([])

  const isPending = (id: number) => pending.value.includes(id)

  const toggleLike = (skill: SkillItem) => {
    if (isPending(skill.id)) return
    pending.value.push(skill.id)

    const prevLiked = skill.liked
    const prevCount = skill.like_count
    skill.liked = !prevLiked
    skill.like_count = Math.max(0, prevCount + (skill.liked ? 1 : -1))

    skillApi
      .toggleLike(skill.id)
      .then((res) => {
        skill.like_count = res.data.like_count
        skill.liked = res.data.liked
      })
      .catch((err: Error) => {
        skill.liked = prevLiked
        skill.like_count = prevCount
        ElMessage.error(err.message || '点赞失败，请稍后重试')
      })
      .finally(() => {
        pending.value = pending.value.filter((id) => id !== skill.id)
      })
  }

  return { toggleLike, isPending }
}
