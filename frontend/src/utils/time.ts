/**
 * 把 'YYYY-MM-DD HH:mm:ss' 形式的时间串转换为相对描述，
 * 用于 Skill 卡片与详情抽屉中展示「更新时间」。
 */
export function relativeTime(value: string): string {
  if (!value) return '—'
  const target = new Date(value.replace(/-/g, '/'))
  if (Number.isNaN(target.getTime())) return value.slice(0, 10)

  const diffMs = Date.now() - target.getTime()
  const diffMin = Math.floor(diffMs / 60000)
  if (diffMin < 1) return '刚刚'
  if (diffMin < 60) return `${diffMin} 分钟前`
  const diffHour = Math.floor(diffMin / 60)
  if (diffHour < 24) return `${diffHour} 小时前`
  const diffDay = Math.floor(diffHour / 24)
  if (diffDay < 30) return `${diffDay} 天前`
  return value.slice(0, 10)
}
