/** Skill 状态与来源的展示元数据，供列表、表格与抽屉复用，避免各处硬编码文案 */

export const SKILL_STATUS_META = {
  0: { label: '草稿', text: 'text-[#FF9F1C]', dot: 'bg-[#FF9F1C]' },
  1: { label: '已上架', text: 'text-[#19BE6B]', dot: 'bg-[#19BE6B]' },
  2: { label: '已下架', text: 'text-ink-mute', dot: 'bg-[#C0C8D6]' },
} as const

export const SKILL_SOURCE_META = {
  form: { label: '表单', cls: 'bg-[#F0F4FD] text-primary-deep' },
  zip: { label: '压缩包', cls: 'bg-[#F4F1FE] text-primary-purple' },
} as const

/** 模板中 el-table 的 row 为 any，用以下取值函数避免以 any 作为索引 */
export const skillStatusMeta = (status: number) =>
  SKILL_STATUS_META[(status as 0 | 1 | 2) ?? 0] ?? SKILL_STATUS_META[0]

export const skillSourceMeta = (source: string) =>
  SKILL_SOURCE_META[source as keyof typeof SKILL_SOURCE_META] ?? SKILL_SOURCE_META.form

export const skillStatusLabel = (status: number) => skillStatusMeta(status).label
