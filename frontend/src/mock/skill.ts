/**
 * AI Skill 平台前端 mock 数据源
 *
 * 后端接口尚未实现时由 `api/index.ts` 按 VITE_USE_MOCK 开关分流到此模块。
 * 返回结构与真实接口保持一致（{ data: ... }），后端就绪后仅切换开关即可平替。
 */
import type {
  SkillCategory,
  SkillCategoryPayload,
  SkillItem,
  SkillLikeResult,
  SkillParam,
  SkillPayload,
  SkillQuery,
  SkillStats,
  SkillZipParseResult,
} from '../types'
import { getRuntimeConfig } from '../config/runtime'
import { useUserStore } from '../stores/user'

/** mock 环境下的作者名单，用于验证「仅可管理自己的 Skill」边界 */
export const MOCK_OWNERS = [
  { id: 1, name: '张伟' },
  { id: 2, name: '李娜' },
  { id: 3, name: '王芳' },
  { id: 4, name: '陈静' },
  { id: 5, name: '刘洋' },
  { id: 6, name: '赵敏' },
  { id: 7, name: '孙磊' },
  { id: 8, name: '周涛' },
]

const ownerName = (id: number) => MOCK_OWNERS.find((o) => o.id === id)?.name ?? '未知'

export const mockCategories: SkillCategory[] = [
  { id: 1, name: '研发', dimension: 'function', icon: 'Code', sort: 10, status: 1 },
  { id: 2, name: '人事', dimension: 'function', icon: 'Users', sort: 20, status: 1 },
  { id: 3, name: '财务', dimension: 'function', icon: 'Calculator', sort: 30, status: 1 },
  { id: 4, name: '市场', dimension: 'function', icon: 'Megaphone', sort: 40, status: 1 },
  { id: 5, name: '法务', dimension: 'function', icon: 'Scale', sort: 50, status: 1 },
  { id: 6, name: '运营', dimension: 'function', icon: 'Workflow', sort: 60, status: 1 },
  { id: 7, name: '行政', dimension: 'function', icon: 'Building2', sort: 70, status: 1 },
  { id: 11, name: '文档处理', dimension: 'type', icon: 'FileText', sort: 10, status: 1 },
  { id: 12, name: '代码助手', dimension: 'type', icon: 'Code', sort: 20, status: 1 },
  { id: 13, name: '数据分析', dimension: 'type', icon: 'BarChart', sort: 30, status: 1 },
  { id: 14, name: '流程自动化', dimension: 'type', icon: 'Workflow', sort: 40, status: 1 },
  { id: 15, name: '知识问答', dimension: 'type', icon: 'BookOpen', sort: 50, status: 1 },
]

/** 按类型分类给出的默认入参，保证 mock 数据具备真实可读性 */
const paramsByType: Record<number, SkillParam[]> = {
  11: [
    { name: 'document', label: '待处理的文档内容', required: true },
    { name: 'style', label: '输出风格偏好', required: false },
  ],
  12: [
    { name: 'code', label: '待处理的代码或仓库路径', required: true },
    { name: 'language', label: '编程语言 / 技术栈', required: false },
  ],
  13: [
    { name: 'dataset', label: '数据范围（库表或时间区间）', required: true },
    { name: 'metric', label: '重点关注指标', required: false },
  ],
  14: [
    { name: 'payload', label: '触发流程的业务数据', required: true },
    { name: 'system', label: '目标系统', required: false },
  ],
  15: [
    { name: 'question', label: '要回答的问题', required: true },
    { name: 'knowledge_base', label: '限定知识范围', required: false },
  ],
}

/** 生成结构化的提示词正文，等价于 SKILL.md 内容 */
const buildContent = (name: string, summary: string) => `# ${name}

## 角色
你是企业内部「${name}」助手，服务于提出请求的同事。

## 任务
${summary}。

## 工作流程
1. 检查入参是否完整，缺失必填项时先向使用者追问，不要猜测
2. 按输出模板组织结果，先给结论再给细节
3. 涉及制度、金额、日期的结论必须标注依据来源
4. 无法确认的内容明确标注「待确认」，不得编造事实

## 输出格式
- 结论摘要：不超过 3 条
- 详细结果：分点列出，包含依据
- 风险与待确认事项`

/** [名称, 一句话描述, 职能分类, 类型分类, 图标, 来源, 作者 id, 状态, 更新时间] */
type Seed = [string, string, number, number, string, SkillItem['source'], number, 0 | 1 | 2, string]

const SEEDS: Seed[] = [
  ['需求文档自动拆解', '将产品需求文档拆解为可执行开发任务，自动补全验收标准与关联模块', 1, 11, 'FileText', 'form', 1, 1, '2026-09-21'],
  ['代码评审助手', '按团队编码规范审查 PR，输出风险等级、问题定位与修改建议清单', 1, 12, 'Code', 'form', 2, 1, '2026-09-18'],
  ['接口测试用例生成', '依据接口定义自动生成正常、边界与异常三类测试用例', 1, 12, 'ListChecks', 'zip', 1, 1, '2026-09-17'],
  ['技术方案模板生成', '按统一模板输出背景、方案对比、灰度与回滚设计', 1, 11, 'FileCode', 'form', 2, 0, '2026-09-16'],
  ['数据库变更评审', '检查建表与索引变更是否命中团队 DDL 规范', 1, 12, 'Database', 'form', 1, 1, '2026-08-29'],
  ['缺陷根因分析', '结合日志与近期变更推断缺陷的候选根因并给出验证顺序', 1, 13, 'Bug', 'zip', 1, 2, '2026-09-10'],
  ['招聘 JD 生成器', '依据岗位与职级生成结构化 JD，对齐公司统一能力模型描述', 2, 11, 'Users', 'form', 3, 1, '2026-09-20'],
  ['面试评价汇总', '汇总多轮面试官评语，输出能力维度得分与录用建议', 2, 11, 'ClipboardList', 'form', 3, 1, '2026-09-11'],
  ['新人入职指引问答', '基于人事制度文档回答入职流程、账号与福利类高频问题', 2, 15, 'BookOpen', 'form', 1, 1, '2026-09-05'],
  ['员工关怀话术生成', '按场景生成关怀沟通话术，避免敏感表述与过度承诺', 2, 15, 'HeartHandshake', 'form', 3, 0, '2026-09-14'],
  ['费用报销合规校验', '逐条比对报销单据与费用制度，标出不合规项并给出条款依据', 3, 14, 'Calculator', 'form', 4, 1, '2026-09-19'],
  ['差旅报销智能填报', '按发票与行程信息自动生成报销单草稿并预填科目', 3, 14, 'Receipt', 'zip', 4, 0, '2026-09-17'],
  ['月度经营分析摘要', '汇总收入、成本与费用数据，输出带同比环比的经营摘要', 3, 13, 'BarChart', 'form', 4, 1, '2026-09-08'],
  ['竞品分析速览', '汇总公开信息生成竞品对比矩阵，提炼差异化卖点与机会窗口', 4, 13, 'TrendingUp', 'form', 5, 1, '2026-09-19'],
  ['客户拜访纪要提炼', '从拜访记录中提取客户需求、决策链与下一步跟进事项', 4, 11, 'Megaphone', 'form', 5, 1, '2026-09-16'],
  ['营销文案合规审查', '检查对外文案中的绝对化用语与广告法风险表述', 4, 15, 'BadgeCheck', 'zip', 5, 1, '2026-09-02'],
  ['合同条款风险扫描', '识别付款、违约与知识产权条款中的高风险表述并给出修改建议', 5, 15, 'Scale', 'zip', 6, 1, '2026-09-19'],
  ['用印申请要素检查', '核对用印材料是否齐全，指出缺失要素与补件路径', 5, 14, 'Stamp', 'form', 6, 1, '2026-09-06'],
  ['数据合规自查清单', '按个人信息保护要求输出业务数据处理自查项与整改建议', 5, 11, 'ShieldCheck', 'form', 6, 2, '2026-08-25'],
  ['周报数据自动汇总', '聚合多渠道运营数据，生成带同比环比结论的结构化周报初稿', 6, 11, 'Workflow', 'form', 7, 1, '2026-09-22'],
  ['取数 SQL 生成器', '用自然语言描述指标，生成符合数仓规范的 SQL 与字段口径说明', 6, 13, 'Database', 'form', 8, 1, '2026-09-13'],
  ['生产排期优化建议', '结合产能、在制与交期给出可行排期方案与冲突提示', 6, 13, 'CalendarClock', 'zip', 7, 2, '2026-09-12'],
  ['团队 OKR 梳理助手', '把业务目标拆解为可度量的关键结果并标注负责人', 6, 15, 'Target', 'form', 1, 0, '2026-09-15'],
  ['会议纪要结构化', '把会议记录整理为议题、结论、待办与责任人四段式纪要', 7, 11, 'NotebookPen', 'form', 8, 1, '2026-09-09'],
  ['办公资产盘点核对', '比对台账与盘点结果，输出盘盈盘亏明细与处理建议', 7, 14, 'Boxes', 'zip', 7, 1, '2026-09-01'],
]

let idCursor = 100
const nextId = () => ++idCursor

/**
 * 点赞记录：user_id -> 已点赞的 skill_id 集合。
 * 真实后端对应 `ai_skill_like` 表，(user_id, skill_id) 建唯一索引，保证每人仅计一次。
 */
const likes = new Map<number, Set<number>>()

/** mock 环境下的「当前登录用户」；真实接口由后端从 token 解析，无需前端传参 */
const currentUserId = () => useUserStore().profile?.id ?? 1

const likedBy = (userId: number) => {
  let set = likes.get(userId)
  if (!set) {
    set = new Set<number>()
    likes.set(userId, set)
  }
  return set
}

/** 初始点赞数：由 id 派生的稳定伪随机值，保证刷新后热度排序不跳动 */
const seedLikes = (id: number) => ((id * 37) % 89) + 6

let skills: SkillItem[] = SEEDS.map(([name, summary, fnId, tyId, icon, source, ownerId, status, updatedAt]) => {
  const id = nextId()
  return {
    id,
    name,
    icon,
    summary,
    function_category_id: fnId,
    type_category_id: tyId,
    content: buildContent(name, summary),
    params: paramsByType[tyId] ?? [],
    source,
    // skill 包路径为相对 skills 主目录的路径（如 skills/101/package.zip），展示时拼 local_path 前缀
    package_path:
      source === 'zip' ? `${getRuntimeConfig().skill.local_path.replace(/\/+$/, '')}/${id}/package.zip` : null,
    owner_id: ownerId,
    owner_name: ownerName(ownerId),
    status,
    like_count: seedLikes(id),
    liked: false,
    created_at: `${updatedAt} 09:30:00`,
    updated_at: `${updatedAt} 09:30:00`,
  }
})

let categories: SkillCategory[] = mockCategories.map((c) => ({ ...c }))

/** 模拟网络耗时，返回体与 axios 拦截器后的结构一致 */
const ok = <T>(data: T, ms = 260): Promise<{ data: T }> =>
  new Promise((resolve) => setTimeout(() => resolve({ data }), ms))

const fail = (msg: string): Promise<never> => Promise.reject(new Error(msg))

const MAX_ZIP_SIZE = 50 * 1024 * 1024

// ---------- 分类 ----------

export function mockListCategories(dimension?: SkillCategory['dimension']) {
  const list = categories
    .filter((c) => !dimension || c.dimension === dimension)
    .sort((a, b) => a.sort - b.sort || a.id - b.id)
  return ok([...list])
}

export function mockCreateCategory(data: SkillCategoryPayload) {
  if (categories.some((c) => c.name === data.name && c.dimension === data.dimension)) {
    return fail('该维度下已存在同名分类')
  }
  const item: SkillCategory = { ...data, id: nextId() }
  categories.push(item)
  return ok({ ...item })
}

export function mockUpdateCategory(id: number, data: SkillCategoryPayload) {
  const target = categories.find((c) => c.id === id)
  if (!target) return fail('分类不存在或已被删除')
  Object.assign(target, data)
  return ok({ ...target })
}

export function mockRemoveCategory(id: number) {
  const used = skills.some((s) => s.function_category_id === id || s.type_category_id === id)
  if (used) return fail('该分类下仍有 Skill，请先调整或下架后再删除')
  const before = categories.length
  categories = categories.filter((c) => c.id !== id)
  if (categories.length === before) return fail('分类不存在或已被删除')
  return ok(null)
}

// ---------- Skill ----------

export function mockListSkills(query: SkillQuery) {
  const keyword = (query.keyword ?? '').trim().toLowerCase()
  let list = skills.filter((s) => {
    if (query.owner_id && s.owner_id !== query.owner_id) return false
    if (query.status !== undefined && query.status !== '' && s.status !== query.status) return false
    if (query.function_category_id && s.function_category_id !== query.function_category_id) return false
    if (query.type_category_id && s.type_category_id !== query.type_category_id) return false
    if (keyword) {
      const haystack = `${s.name}${s.summary}${s.owner_name}${s.content}`.toLowerCase()
      if (!haystack.includes(keyword)) return false
    }
    return true
  })

  // 当前登录用户的点赞态（真实接口由后端按 token 下发）
  const liked = likedBy(currentUserId())

  list = [...list].sort((a, b) => {
    if (query.sort === 'name') return a.name.localeCompare(b.name, 'zh-Hans-CN')
    // 热度排序：点赞数降序，同分时按更新时间兜底，保证顺序稳定
    if (query.sort === 'likes') return b.like_count - a.like_count || b.updated_at.localeCompare(a.updated_at)
    // 最新提交：按创建时间倒序，同分时按 id 兜底
    if (query.sort === 'newest') return b.created_at.localeCompare(a.created_at) || b.id - a.id
    return b.updated_at.localeCompare(a.updated_at)
  })

  const page = Math.max(1, query.page || 1)
  const size = Math.max(1, query.size || 12)
  // 必须返回副本：列表项会被前端就地修改（点赞乐观更新），
  // 若直接交出仓库对象引用，前端的 +1 会污染 mock 数据源，导致服务端再次累加而计数翻倍
  const items = list.slice((page - 1) * size, page * size).map((s) => ({ ...s, liked: liked.has(s.id) }))
  return ok({ total: list.length, list: items })
}

export function mockSkillStats(ownerId?: number) {
  const list = skills.filter((s) => !ownerId || s.owner_id === ownerId)
  const stats: SkillStats = {
    total: list.length,
    published: list.filter((s) => s.status === 1).length,
    draft: list.filter((s) => s.status === 0).length,
    offline: list.filter((s) => s.status === 2).length,
  }
  return ok(stats)
}

export function mockCreateSkill(data: SkillPayload) {
  const ts = new Date().toISOString().slice(0, 19).replace('T', ' ')
  const item: SkillItem = {
    ...data,
    params: data.params ?? [],
    package_path: data.package_path ?? null,
    id: nextId(),
    owner_id: 1,
    owner_name: ownerName(1),
    like_count: 0,
    liked: false,
    created_at: ts,
    updated_at: ts,
  }
  skills.unshift(item)
  return ok({ ...item })
}

export function mockUpdateSkill(id: number, data: SkillPayload) {
  const target = skills.find((s) => s.id === id)
  if (!target) return fail('Skill 不存在或已被删除')
  Object.assign(target, data, {
    params: data.params ?? target.params,
    updated_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
  })
  return ok({ ...target })
}

export function mockRemoveSkill(id: number) {
  const before = skills.length
  skills = skills.filter((s) => s.id !== id)
  if (skills.length === before) return fail('Skill 不存在或已被删除')
  likes.forEach((set) => set.delete(id))
  return ok(null)
}

export function mockSetSkillStatus(id: number, status: 0 | 1 | 2) {
  const target = skills.find((s) => s.id === id)
  if (!target) return fail('Skill 不存在或已被删除')
  target.status = status
  target.updated_at = new Date().toISOString().slice(0, 19).replace('T', ' ')
  return ok({ ...target })
}

/**
 * 点赞 / 取消点赞：同一用户对同一 Skill 仅保留一条记录，重复点击即在「已赞 / 未赞」间切换。
 * 真实后端应基于 (user_id, skill_id) 唯一索引做 upsert / delete，并用事务维护 skill.like_count。
 */
export function mockToggleLike(id: number) {
  const target = skills.find((s) => s.id === id)
  if (!target) return fail('Skill 不存在或已被删除')

  const liked = likedBy(currentUserId())
  const next = !liked.has(id)
  if (next) liked.add(id)
  else liked.delete(id)

  target.like_count = Math.max(0, target.like_count + (next ? 1 : -1))
  target.liked = next

  const result: SkillLikeResult = { like_count: target.like_count, liked: next }
  return ok(result, 160)
}

/**
 * 模拟服务端解析 zip：客户端仅做扩展名与体积校验，
 * 目录树与逐项校验结论由「后端」返回（原型期由本函数给出）。
 */
export function mockParseSkillZip(file: File) {
  if (!file.name.toLowerCase().endsWith('.zip')) {
    return fail('仅支持 .zip 格式的压缩包')
  }
  if (file.size === 0) {
    return fail('压缩包内容为空，请重新打包后上传')
  }
  if (file.size > MAX_ZIP_SIZE) {
    return fail('压缩包体积超过 50MB，请精简 scripts/ 与 resources/ 后再上传')
  }

  const base = file.name.replace(/\.zip$/i, '')
  const withScripts = file.size > 8 * 1024
  const entries: SkillZipParseResult['entries'] = [
    { path: `${base}/`, type: 'dir' },
    { path: `${base}/SKILL.md`, type: 'file' },
    { path: `${base}/scripts/`, type: 'dir' },
    { path: `${base}/resources/`, type: 'dir' },
  ]
  const checks: SkillZipParseResult['checks'] = [
    { label: '根目录包含 SKILL.md', required: true, passed: true, message: '已找到提示词主文件，共 1.4 KB' },
    {
      label: 'scripts/ 目录（可选）',
      required: false,
      passed: withScripts,
      message: withScripts ? '检测到 2 个脚本，将随包一起发布' : '未检测到脚本，将以纯提示词方式运行',
    },
    { label: 'resources/ 目录（可选）', required: false, passed: true, message: '检测到 3 个参考文档' },
    { label: '文件数量不超过 50 个', required: true, passed: true, message: `实际 ${withScripts ? 6 : 4} 个` },
    { label: '单包体积不超过 50MB', required: true, passed: true, message: `${(file.size / 1024 / 1024).toFixed(2)} MB` },
  ]

  const valid = checks.filter((c) => c.required).every((c) => c.passed)
  const result: SkillZipParseResult = {
    valid,
    file_name: file.name,
    size: file.size,
    entries,
    checks,
    package_path: valid ? `${getRuntimeConfig().skill.local_path.replace(/\/+$/, '')}/${file.name}` : null,
    draft: {
      name: base.replace(/[-_]/g, ' '),
      summary: `由压缩包 ${file.name} 解析生成，请确认一句话描述`,
      content: `# ${base}\n\n## 角色\n（由 SKILL.md 解析，可继续编辑）\n\n## 任务\n${base} 的执行步骤说明\n\n## 输出格式\n- 结论摘要\n- 详细结果`,
      params: [
        { name: 'input', label: '输入内容', required: true },
        { name: 'scope', label: '适用范围', required: false },
      ],
      icon: 'Package',
    },
  }
  return ok(result)
}
