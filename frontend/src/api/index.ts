import request from './request'
import { useSkillMock } from '../config/runtime'
import {
  mockCreateCategory,
  mockCreateSkill,
  mockListCategories,
  mockListSkills,
  mockParseSkillZip,
  mockRemoveCategory,
  mockRemoveSkill,
  mockSetSkillStatus,
  mockSkillStats,
  mockToggleLike,
  mockUpdateCategory,
  mockUpdateSkill,
} from '../mock/skill'
import type {
  AnnouncementItem,
  AppLinkItem,
  PageResult,
  PermissionNode,
  PortalThemeConfig,
  PortalThemeItem,
  RoleItem,
  SkillCategory,
  SkillCategoryPayload,
  SkillItem,
  SkillLikeResult,
  SkillQuery,
  SkillStats,
  SkillPayload,
  SkillZipParseResult,
  SummaryStat,
  UserItem,
  UserProfile,
} from '../types'

// ---------- 认证 ----------
export const authApi = {
  login: (username: string, password: string) =>
    request.post('/auth/login', { username, password }) as Promise<{ data: { token: string; user: UserProfile } }>,
  logout: () => request.post('/auth/logout'),
  profile: () => request.get('/auth/profile') as Promise<{ data: UserProfile }>,
}

// ---------- 门户 ----------
export const portalApi = {
  summary: () => request.get('/portal/summary') as Promise<{ data: SummaryStat }>,
  home: () =>
    request.get('/portal/home') as Promise<{
      data: { links: { collab: AppLinkItem[]; custom: AppLinkItem[] }; announcements: AnnouncementItem[] }
    }>,
  theme: () => request.get('/portal/theme') as Promise<{ data: Record<string, PortalThemeConfig> }>,
  announcements: (params: { page: number; size: number; keyword?: string }) =>
    request.get('/portal/announcements', { params }) as Promise<{ data: { total: number; list: AnnouncementItem[] } }>,
  announcementDetail: (id: number) =>
    request.get(`/portal/announcements/${id}`) as Promise<{ data: AnnouncementItem }>,
}

// ---------- 门户背景图配置 ----------
export const portalThemeApi = {
  list: () => request.get('/admin/portal-themes') as Promise<{ data: { list: PortalThemeItem[] } }>,
  save: (data: { module: string; image_path: string; opacity: number }) =>
    request.post('/admin/portal-themes', data),
  upload: (module: string, file: File) => {
    const form = new FormData()
    form.append('module', module)
    form.append('file', file)
    return request.post('/admin/portal-themes/upload', form) as Promise<{ data: { image_path: string } }>
  },
  images: (module: string) =>
    request.get('/admin/portal-themes/images', { params: { module } }) as Promise<{ data: { list: string[] } }>,
  /** 删除某张历史背景图：移入回收站，不动当前配置 */
  removeImage: (module: string, path: string) =>
    request.delete('/admin/portal-themes/images', { data: { module, path } }),
}

// ---------- 账号管理 ----------
export const userApi = {
  list: (params: Record<string, unknown>) =>
    request.get('/admin/users', { params }) as Promise<{ data: PageResult<UserItem> }>,
  create: (data: Record<string, unknown>) => request.post('/admin/users', data),
  update: (id: number, data: Record<string, unknown>) => request.put(`/admin/users/${id}`, data),
  remove: (id: number) => request.delete(`/admin/users/${id}`),
  resetPassword: (id: number, password?: string) =>
    request.post(`/admin/users/${id}/reset-password`, password ? { password } : {}) as Promise<{
      data: { password: string }
    }>,
}

// ---------- 角色权限 ----------
export const roleApi = {
  list: (params: Record<string, unknown>) =>
    request.get('/admin/roles', { params }) as Promise<{ data: PageResult<RoleItem> }>,
  create: (data: Record<string, unknown>) => request.post('/admin/roles', data),
  update: (id: number, data: Record<string, unknown>) => request.put(`/admin/roles/${id}`, data),
  remove: (id: number) => request.delete(`/admin/roles/${id}`),
  assignPermissions: (id: number, permissionIds: number[]) =>
    request.post(`/admin/roles/${id}/permissions`, { permission_ids: permissionIds }),
  permissionTree: () => request.get('/admin/permissions') as Promise<{ data: PermissionNode[] }>,
}

// ---------- 应用链接 ----------
export const appLinkApi = {
  list: (params: Record<string, unknown>) =>
    request.get('/admin/app-links', { params }) as Promise<{ data: PageResult<AppLinkItem> }>,
  create: (data: Record<string, unknown>) => request.post('/admin/app-links', data),
  update: (id: number, data: Record<string, unknown>) => request.put(`/admin/app-links/${id}`, data),
  remove: (id: number) => request.delete(`/admin/app-links/${id}`),
}

// ---------- AI Skill 平台 ----------
/**
 * 是否走 mock 由后端 myconfig.skill.use_mock 决定（经 GET /api/config 下发）；
 * 本地调试可用 .env 的 VITE_USE_MOCK=true|false 强制覆盖。
 */

export const skillApi = {
  /** 列表查询：广场传 status=1，我的 Skill 额外传 owner_id，后台不传则查全量 */
  list: (params: SkillQuery): Promise<{ data: PageResult<SkillItem> }> =>
    useSkillMock() ? mockListSkills(params) : (request.get('/skills', { params }) as Promise<{ data: PageResult<SkillItem> }>),
  /** 统计：传 ownerId 为个人维度，不传为全平台维度 */
  stats: (ownerId?: number): Promise<{ data: SkillStats }> =>
    useSkillMock()
      ? mockSkillStats(ownerId)
      : (request.get('/skills/stats', { params: { owner_id: ownerId } }) as Promise<{ data: SkillStats }>),
  create: (data: SkillPayload): Promise<{ data: SkillItem }> =>
    useSkillMock() ? mockCreateSkill(data) : (request.post('/skills', data) as Promise<{ data: SkillItem }>),
  update: (id: number, data: SkillPayload): Promise<{ data: SkillItem }> =>
    useSkillMock() ? mockUpdateSkill(id, data) : (request.put(`/skills/${id}`, data) as Promise<{ data: SkillItem }>),
  remove: (id: number): Promise<{ data: null }> =>
    useSkillMock() ? mockRemoveSkill(id) : (request.delete(`/skills/${id}`) as Promise<{ data: null }>),
  /** 上下架 / 存草稿：status 0 草稿 1 上架 2 下架 */
  setStatus: (id: number, status: 0 | 1 | 2): Promise<{ data: SkillItem }> =>
    useSkillMock()
      ? mockSetSkillStatus(id, status)
      : (request.post(`/skills/${id}/status`, { status }) as Promise<{ data: SkillItem }>),
  /**
   * 点赞 / 取消点赞：服务端按 (user_id, skill_id) 唯一约束切换，同一用户重复点击即在两种状态间翻转。
   * 返回最新的点赞总数与本人点赞态，前端以服务端结果为准。
   */
  toggleLike: (id: number): Promise<{ data: SkillLikeResult }> =>
    useSkillMock()
      ? mockToggleLike(id)
      : (request.post(`/skills/${id}/like`) as Promise<{ data: SkillLikeResult }>),
  /** 上传 zip：服务端解析目录结构并返回校验结论，校验通过后回填表单 */
  parseZip: (file: File): Promise<{ data: SkillZipParseResult }> => {
    if (useSkillMock()) return mockParseSkillZip(file)
    const form = new FormData()
    form.append('file', file)
    return request.post('/skills/parse-zip', form) as Promise<{ data: SkillZipParseResult }>
  },
}

// ---------- AI Skill 分类 ----------
export const skillCategoryApi = {
  list: (dimension?: SkillCategory['dimension']): Promise<{ data: SkillCategory[] }> =>
    useSkillMock()
      ? mockListCategories(dimension)
      : (request.get('/skills/categories', { params: { dimension } }) as Promise<{ data: SkillCategory[] }>),
  create: (data: SkillCategoryPayload): Promise<{ data: SkillCategory }> =>
    useSkillMock()
      ? mockCreateCategory(data)
      : (request.post('/admin/ai-skill-categories', data) as Promise<{ data: SkillCategory }>),
  update: (id: number, data: SkillCategoryPayload): Promise<{ data: SkillCategory }> =>
    useSkillMock()
      ? mockUpdateCategory(id, data)
      : (request.put(`/admin/ai-skill-categories/${id}`, data) as Promise<{ data: SkillCategory }>),
  remove: (id: number): Promise<{ data: null }> =>
    useSkillMock() ? mockRemoveCategory(id) : (request.delete(`/admin/ai-skill-categories/${id}`) as Promise<{ data: null }>),
}

// ---------- 公告 ----------
export const announcementApi = {
  list: (params: Record<string, unknown>) =>
    request.get('/admin/announcements', { params }) as Promise<{ data: PageResult<AnnouncementItem> }>,
  create: (data: Record<string, unknown>) => request.post('/admin/announcements', data),
  update: (id: number, data: Record<string, unknown>) => request.put(`/admin/announcements/${id}`, data),
  remove: (id: number) => request.delete(`/admin/announcements/${id}`),
  publish: (id: number) => request.post(`/admin/announcements/${id}/publish`),
  offline: (id: number) => request.post(`/admin/announcements/${id}/offline`),
  /** 置顶/取消置顶：isTop 1 置顶，0 取消 */
  top: (id: number, isTop: 0 | 1) => request.post(`/admin/announcements/${id}/top`, { is_top: isTop }),
  /** 上传正文图片：announcementId 为 0 表示公告尚未保存，先存临时目录 */
  upload: (announcementId: number, file: File) => {
    const form = new FormData()
    form.append('announcement_id', String(announcementId))
    form.append('file', file)
    return request.post('/admin/announcements/upload', form) as Promise<{ data: { url: string } }>
  },
}
