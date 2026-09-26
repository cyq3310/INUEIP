# 企业内部 AI Skill 管理平台 · 设计方案

> 依托「吟游廊 EIP 平台」现有门户 + 后台体系，新增企业内部 AI Skill 的**沉淀、分类、发布与管控**能力。
> 视觉与交互完全沿用平台既有设计语言，不引入新依赖。

## 1. 目标与范围

| 项目 | 说明 |
|---|---|
| 核心目标 | 让企业内部沉淀的 AI 能力（提示词 / 脚本 / 资源包）可被分类检索、可被复用、可被治理 |
| 使用者 | 全体员工（浏览与使用）、上传者（管理自己的）、管理员（管理全部与分类体系） |
| 本期做 | 职能 + 类型双维分类、上传（表单 / zip）、个人自助管理、管理员全量管控、分类维护、上下架 |
| 本期不做 | 审核流、版本历史、可见范围（私密 / 部门）、调用统计与用量计费 |

## 2. 信息架构与页面地图

```
门户（PortalLayout，全员可访问）
├── /                     首页（新增「AI Skill 推荐」区块，无数据整块隐藏）
├── /skills               AI Skill 广场：发现与检索
└── /skills/my            我的 Skill：上传者自助管理

后台（AdminLayout，需后台权限）
├── /admin/ai-skills              全量管理：跨作者管控（perm: ai-skill:manage-all）
└── /admin/ai-skill-categories    分类管理：维护双维分类（perm: ai-skill:category）
```

> **约束说明**：`/admin/*` 由路由守卫要求 `isAdmin` 且校验 `meta.perm`，普通员工（如 `zhangsan`）无任何权限码，无法进入后台。
> 因此「广场」与「我的 Skill」必须挂在门户路由下，员工自助管理能力不依赖后台权限码，而是按 `owner_id === 当前用户` 判定。

## 3. 角色与权限矩阵

| 能力 | 普通员工 | 上传者（本人） | 管理员 |
|---|:--:|:--:|:--:|
| 浏览广场已上架 Skill | ✅ | ✅ | ✅ |
| 查看 Skill 详情与提示词正文 | ✅ | ✅ | ✅ |
| 上传 Skill（表单 / zip） | ✅ | ✅ | ✅ |
| 编辑 / 上下架 / 删除自己的 Skill | — | ✅ | ✅ |
| 编辑 / 上下架 / 删除他人的 Skill | — | — | ✅ |
| 维护职能与类型分类 | — | — | ✅（需 `ai-skill:category`） |
| 进入后台全量管理页 | — | — | ✅（需 `ai-skill:manage-all`） |

**权限码约定**（与现有 `system:*` 体系一致）：

- `ai-skill:manage-all`：后台「AI Skill 管理」菜单与页面
- `ai-skill:category`：后台「Skill 分类」菜单与页面
- 员工侧上传与自管理**不设权限码**，登录后即可使用

> ⚠️ **待补**：后端权限树 seed 中尚未包含 `ai-skill:*` 两个权限码。原型期仅超管（`isSuperAdmin`）可见这两个菜单，
> 正式启用前需在 `inue_permission` 表中补充并分配给对应角色。

## 4. 分类体系（职能 × 类型）

采用**双维度正交归档**，避免单一维度下 Skill 数量膨胀后难以定位。

| 维度 | 说明 | 默认取值 |
|---|---|---|
| 职能（`dimension = 'function'`） | 按部门 / 岗位归档，回答「给谁用」 | 研发、人事、财务、市场、法务、运营、行政 |
| 类型（`dimension = 'type'`） | 按能力形态归档，回答「怎么用」 | 文档处理、代码助手、数据分析、流程自动化、知识问答 |

- 分类由管理员在后台维护：名称、图标（lucide）、排序、启停
- 停用分类不出现在广场筛选与上传表单中；**已被引用**的分类禁止删除（给出明确错误而非静默失败）
- 广场的职能分类导航展示各分类下「已上架」数量，计数独立拉取，避免翻页时跳动

## 5. 数据模型

```ts
interface SkillCategory {
  id: number
  name: string                          // 如「研发」「文档处理」
  dimension: 'function' | 'type'
  icon: string                          // lucide 图标名
  sort: number
  status: number                        // 1 启用 0 停用
}

interface SkillParam {
  name: string                          // 参数名，如 doc_url
  label: string                         // 说明，如 需求文档链接
  required: boolean
}

interface SkillItem {
  id: number
  name: string
  icon: string
  summary: string                       // 一句话描述
  function_category_id: number
  type_category_id: number
  content: string                       // 提示词正文，等价于 SKILL.md
  params: SkillParam[]
  source: 'form' | 'zip'                // 创建方式
  package_path: string | null           // source=zip 时的包路径
  owner_id: number
  owner_name: string
  status: 0 | 1 | 2                     // 0 草稿 1 已上架 2 已下架
  created_at: string
  updated_at: string
}
```

数据库侧建议（`inue_` 前缀）：`inue_ai_skill`、`inue_ai_skill_category`，`params` 以 JSON 列存储。

## 6. 状态机（基础版）

```
        ┌──────────────── 上架（1）──────────────┐
        │                                        │
   创建 / 保存为草稿 ──▶ 草稿（0）            已上架（1）──▶ 广场可见、全员可检索
        │                                        │
        └──────────────── 下架（2）◀─────────────┘
                              │
                              └──▶ 已下架（2）：仅作者与管理员可见，广场隐藏
```

- 状态切换为**瞬时操作**，无审核环节（本期范围外）
- 广场固定只查询 `status = 1`；「我的 Skill」与后台按状态 Tab 过滤全量
- 删除为物理删除，走二次确认弹窗

## 7. 上传流程

### 7.1 在线表单模式

填写名称 → 图标（IconPicker）→ 职能 / 类型 → 一句话描述 → 提示词正文 → 参数列表（动态增删行）
→ 保存为草稿（`status=0`）或保存并上架（`status=1`）

### 7.2 压缩包模式

```
选择 / 拖拽 .zip
      │
      ▼
客户端预校验（扩展名、体积 ≤ 20MB、非空）
      │
      ▼
服务端解析 → 返回目录树 + 逐项校验结论
      │
      ├─ 必填项未通过 → 红色阻断，逐项列出原因，禁止提交
      │
      └─ 通过 → 「回填表单并继续编辑」→ 切回表单模式，自动填充
                 名称 / 描述 / 正文 / 参数 / 图标，可再编辑后提交
```

**目录规范**

```
my-skill/
├── SKILL.md          # 必填：提示词主文件
├── scripts/          # 可选：随包脚本
└── resources/        # 可选：参考文档
```

**设计取舍**：不在前端引入 JSZip 做真实解压。客户端只做扩展名 / 体积 / 文件数等廉价校验，
目录树与校验结论由服务端返回。职责划分与真实后端一致，也避免前端「假校验」误导用户。
原型期由 `src/mock/skill.ts` 模拟该响应。

## 8. 界面线框与交互状态

### 8.1 AI Skill 广场 `/skills`

```
┌────────────────────────────────────────────────────────────┐
│ 顶栏：首页 / 公告 / AI Skill（激活）                         │
├────────────────────────────────────────────────────────────┤
│ 渐变 Hero：AI Skill 广场 + 搜索框 + 「我的 Skill」入口       │
│ 职能胶囊：[全部 96] [研发 36] [人事 18] [财务 14] …          │
│ 筛选条：共 N 个已上架 Skill        类型▾  排序▾             │
│ 卡片网格（1/2/3/4 列响应式）：                              │
│   ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐      │
│   │ [icon]研发│ │ …        │ │ …        │ │ …        │      │
│   │ 名称      │ │          │ │          │ │          │      │
│   │ 两行描述  │ │          │ │          │ │          │      │
│   │ 文档处理  张伟·2天前                                    │
│   └──────────┘ └──────────┘ └──────────┘ └──────────┘      │
└────────────────────────────────────────────────────────────┘
       点击卡片 → 右侧抽屉：描述 / 提示词正文（可复制）/ 参数表 / 来源 / 资源包
```

### 8.2 我的 Skill `/skills/my`

统计条（全部 / 已上架 / 草稿 / 已下架）→ 工具条（搜索 + 状态筛选 + 上传 Skill）→ 表格
（Skill / 职能 / 类型 / 来源 / 状态 / 更新时间 / 操作：编辑 · 上架下架 · 删除）

### 8.3 后台全量管理 `/admin/ai-skills`

统计卡（总数 / 已上架 / 草稿待处理 / 覆盖职能）→ 状态 Tab → 筛选（关键词含作者、职能、类型）
→ 表格（多「作者」列；操作：编辑 · 上架下架 · 删除）

### 8.4 分类管理 `/admin/ai-skill-categories`

双 Tab（职能 / 类型）→ 表格（图标 / 名称 / 排序 / 启停开关 / 操作）→ 弹窗（名称 / 图标 / 排序 / 状态）

### 8.5 交互状态清单

| 状态 | 表现 |
|---|---|
| 加载 | 列表 `v-loading`；卡片区骨架遮罩 |
| 空 | 插图 + 说明文案 + 引导按钮（广场引导去上传，我的 Skill 引导上传第一个） |
| 错误 | 红色文案 + 「重新加载」按钮（不静默失败） |
| 无权限 | 后台菜单按权限码隐藏；路由守卫回落到第一个有权限的后台页 |
| 删除 | `ElMessageBox.confirm` 二次确认；分类被占用时后端报错并提示原因 |
| 成功 / 失败 | `ElMessage` 提示，明确说明影响（如「已上架，全员可在广场看到」） |

## 9. 视觉规范

沿用平台既有令牌，不引入新视觉语言：

| 用途 | 值 |
|---|---|
| 主色渐变 | `#5B8FF9 → #7A6FF0` |
| 主色深 / 紫 | `#3B6FE0` / `#7A6FF0` |
| 文字 | `#1F2D3D` / `#5E6D82` / `#9099A8` |
| 背景 | 门户 `#F5F7FA`，后台 `#EEF2FB`，卡片 `#FFFFFF` |
| 卡片 | `rounded-2xl` + `shadow-card`，hover 切换 `shadow-card-hover` 并上移 4px |
| 功能色 | 成功 `#19BE6B`、警告 `#FF9F1C`、危险 `#F5222D` |
| 图标 | `lucide-vue-next`，经 `components/LucideIcon.vue` 动态渲染 |

信息密度为**中等**：门户广场偏宽松（4 列卡片），后台表格可紧凑。
响应式：≥1440px 四列，1024–1440px 三列，768–1024px 两列，<768px 单列；后台表格横向滚动、操作列固定右侧。

## 10. 技术实现要点

| 层级 | 文件 | 说明 |
|---|---|---|
| 类型 | `frontend/src/types/index.ts` | `SkillItem` / `SkillCategory` / `SkillParam` / `SkillQuery` / `SkillPayload` / `SkillZipParseResult` |
| 数据 | `frontend/src/mock/skill.ts` | 内存态 mock（25 条多作者多状态数据），覆盖「只能管理自己」边界 |
| 接口 | `frontend/src/api/index.ts` | `skillApi`（list / stats / create / update / remove / setStatus / parseZip）、`skillCategoryApi` |
| 复用 | `frontend/src/composables/useSkillCategories.ts` | 职能 / 类型分类加载与 id→名称映射 |
| 复用 | `frontend/src/config/skill-meta.ts` | 状态与来源的展示元数据 |
| 组件 | `components/SkillCard.vue` | 卡片，广场与首页推荐共用 |
| 组件 | `components/SkillCategoryNav.vue` | 职能胶囊导航（含计数） |
| 组件 | `components/SkillDetailDrawer.vue` | 详情抽屉，广场 / 我的 / 后台共用 |
| 组件 | `components/SkillFormDialog.vue` | 新建 / 编辑，表单与 zip 双模式 |
| 页面 | `views/portal/Skills.vue`、`views/portal/MySkills.vue` | 门户侧 |
| 页面 | `views/admin/AiSkills.vue`、`views/admin/AiSkillCategories.vue` | 后台侧 |
| 入口 | `layout/PortalLayout.vue`、`layout/AdminLayout.vue`、`views/portal/Home.vue` | 顶栏导航、侧栏菜单、首页推荐区块 |
| 路由 | `router/index.ts` | 门户 2 条 + 后台 2 条（含 `meta.perm`） |

### mock 开关

后端接口未就绪时，`api/index.ts` 按环境变量分流：

```
默认（未配置）          → 走 src/mock/skill.ts
VITE_USE_MOCK=false    → 走真实 /api 接口
```

api 层函数签名与 `{ code, msg, data }` / `PageResult<T>` 契约保持不变，后端就绪后仅切换开关即可平替，页面代码零改动。

## 11. 后续待办

1. **权限码 seed**：在权限树中补充 `ai-skill:manage-all`、`ai-skill:category` 并分配给角色
2. **后端接口**：按 `api/index.ts` 中注释的真实路径实现（`/skills*`、`/admin/ai-skill-categories*`）
3. **zip 解析**：服务端解压校验 `SKILL.md`、限制体积与文件数，落盘并返回目录树
4. **归档存储**：`package_path` 目前为 mock 字符串，需对接对象存储或本地上传目录
5. **可选增强**：审核流、版本历史、可见范围、调用统计（本期明确不做，按需迭代）
