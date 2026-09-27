export interface UserProfile {
  id: number
  username: string
  nickname: string
  avatar: string | null
  roles: string[]
  isSuperAdmin: boolean
  permissions: string[]
}

export interface AppLinkItem {
  id: number
  name: string
  icon: string
  subtitle: string
  url: string
  section?: 'collab' | 'custom'
  sort?: number
  status?: number
  created_at?: string
}

export interface AnnouncementItem {
  id: number
  title: string
  summary: string
  content?: string
  status?: number
  /** 是否置顶：1 置顶，0 未置顶 */
  is_top?: number
  /** 置顶时间，取消置顶后为 null */
  topped_at?: string | null
  published_at: string | null
  created_at?: string
}

export interface SummaryStat {
  unclock: number
  late: number
  pending: number
}

/** 后台可配置的门户分区背景项 */
export interface PortalThemeItem {
  module: string
  name: string
  image_path: string | null
  opacity: number
}

/** 门户端读取的背景配置 */
export interface PortalThemeConfig {
  image_path: string
  opacity: number
}

export interface RoleItem {
  id: number
  name: string
  code: string
  description: string
  status: number
  created_at: string
  permission_ids: number[]
}

export interface PermissionNode {
  id: number
  parent_id: number
  name: string
  code: string
  type: 'menu' | 'api'
  path: string
  platform?: string
  children?: PermissionNode[]
}

/** 按平台分组的权限：平台页签 → 功能菜单 → 接口 */
export interface PermissionPlatform {
  platform: string
  name: string
  menus: PermissionNode[]
}

export interface UserItem {
  id: number
  username: string
  nickname: string
  avatar: string | null
  status: number
  last_login_at: string | null
  created_at: string
  roles: { id: number; name: string; code: string }[]
}

export interface PageResult<T> {
  total: number
  list: T[]
}

// ---------- AI Skill 平台 ----------

/** Skill 创建方式：在线表单 / 压缩包上传 */
export type SkillSource = 'form' | 'zip'

/** Skill 运行时入参定义 */
export interface SkillParam {
  name: string
  label: string
  required: boolean
}

/** 分类维度：function=职能，type=类型 */
export type SkillCategoryDimension = 'function' | 'type'

export interface SkillCategory {
  id: number
  name: string
  dimension: SkillCategoryDimension
  icon: string
  sort: number
  /** 1 启用 0 停用 */
  status: number
}

export interface SkillItem {
  id: number
  name: string
  icon: string
  summary: string
  function_category_id: number
  type_category_id: number
  /** 提示词正文，等价于 SKILL.md 内容 */
  content: string
  params: SkillParam[]
  source: SkillSource
  /** source=zip 时的包路径（相对 skills 主目录，如 skills/101/package.zip），其余为 null */
  package_path: string | null
  owner_id: number
  owner_name: string
  /** 0 草稿 1 已上架 2 已下架 */
  status: 0 | 1 | 2
  /** 点赞总数 */
  like_count: number
  /** 当前登录用户是否已点赞；后端按 (user_id, skill_id) 唯一约束保证每人仅计一次 */
  liked: boolean
  created_at: string
  updated_at: string
}

/** 列表查询参数，各页面按需传字段 */
export interface SkillQuery {
  page: number
  size: number
  keyword?: string
  function_category_id?: number | ''
  type_category_id?: number | ''
  status?: 0 | 1 | 2 | ''
  /** 指定时仅返回该作者的 Skill（我的 Skill 用当前登录者 id） */
  owner_id?: number
  /** latest=最近更新（按 updated_at），newest=最新提交（按 created_at），likes=最多点赞 */
  sort?: 'latest' | 'newest' | 'name' | 'likes'
}

/** 点赞 / 取消点赞的返回体：计数与本人点赞态 */
export interface SkillLikeResult {
  like_count: number
  liked: boolean
}

export interface SkillStats {
  total: number
  published: number
  draft: number
  offline: number
}

/** 新建 / 编辑 Skill 的提交载荷 */
export type SkillPayload = Pick<
  SkillItem,
  'name' | 'icon' | 'summary' | 'function_category_id' | 'type_category_id' | 'content' | 'params' | 'source' | 'status'
> & { package_path?: string | null }

/** 新建 / 编辑分类的提交载荷 */
export type SkillCategoryPayload = Pick<SkillCategory, 'name' | 'dimension' | 'icon' | 'sort' | 'status'>

/** zip 包结构校验项 */
export interface SkillZipCheck {
  label: string
  required: boolean
  passed: boolean
  message: string
}

/** zip 包解析（含校验）结果 */
export interface SkillZipParseResult {
  valid: boolean
  file_name: string
  size: number
  entries: { path: string; type: 'file' | 'dir' }[]
  checks: SkillZipCheck[]
  /** 校验通过后服务端落盘的相对路径；未通过为 null，提交时用此值写入 package_path */
  package_path: string | null
  /** 解析出的可回填草稿 */
  draft: { name: string; summary: string; content: string; params: SkillParam[]; icon: string }
}
