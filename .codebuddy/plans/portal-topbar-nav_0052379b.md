---
name: portal-topbar-nav
overview: 在门户顶栏（PortalLayout）加入「首页」「公告」两个导航按钮：首页跳回主页面（/），公告为占位（toast 提示“开发中”，暂不实装页面）；并把“后续页面开发默认保留顶栏用于快速跳转”固化为项目规则文件，之后自动遵循。
design:
  architecture:
    framework: vue
  styleKeywords:
    - Consistent
    - Minimal
    - Aligned
  fontSystem:
    fontFamily: PingFang SC
    heading:
      size: 16px
      weight: 500
    subheading:
      size: 14px
      weight: 500
    body:
      size: 14px
      weight: 400
  colorSystem:
    primary:
      - "#5B8FF9"
      - "#7A6FF0"
    background:
      - "#FFFFFF"
      - "#F5F7FA"
    text:
      - "#1F2D3D"
      - "#5E6D82"
      - "#9099A8"
    functional:
      - "#3B6FE0"
      - "#F3F4F6"
todos:
  - id: add-topbar-buttons
    content: 在 PortalLayout 顶栏新增「首页」「公告」按钮（含图标与激活态样式）
    status: completed
  - id: wire-nav-actions
    content: 实现首页路由跳转与公告 toast 占位逻辑
    status: completed
    dependencies:
      - add-topbar-buttons
  - id: create-rule-doc
    content: 新建 .codebuddy/rules 门户顶栏规范文档固化标准
    status: completed
  - id: verify-topbar
    content: 启动 dev 验证按钮交互与顶栏跨页保留
    status: completed
    dependencies:
      - add-topbar-buttons
      - wire-nav-actions
---

## 用户需求

在门户顶栏（PortalLayout）新增「首页」「公告」两个导航按钮，并固化一条后续开发标准。

## 产品概述

门户主页面顶部导航栏当前仅含可点击 Logo 与右侧用户菜单。现需在顶栏中加入显式的「首页」「公告」入口，提升导航可见性；同时把“后续页面开发默认保留顶栏以实现快速跳转”固化为项目规则，供之后自动遵循。

## 核心特性

- 顶栏新增「首页」按钮：点击返回主页面（`/`），在当前为首页时高亮激活态。
- 顶栏新增「公告」按钮：本次仅占位，点击弹出 toast 提示“公告页面开发中”，暂不跳转、不实装页面。
- 顶栏作为 PortalLayout 的持久外壳，新增按钮自动出现在门户下所有后续页面，满足“保留顶栏快速跳转”要求。
- 新建项目规则文件 `.codebuddy/rules/门户顶栏规范.md`，明确“门户页面默认沿用 PortalLayout 顶栏、保留首页/公告等快速跳转入口”的标准（除非单次需求另有特殊说明）。

## 技术栈

- 沿用现有前端栈：Vue 3 + TypeScript + Vite + Tailwind CSS + Element Plus + lucide-vue-next，不引入新依赖。
- 规则文档：Markdown，置于 `.codebuddy/rules/`（项目既有 `.codebuddy` 目录，目前无 rules 子目录，需新建）。

## 实现方案

- **改动面最小**：仅修改 `PortalLayout.vue`（顶栏），新增按钮会出现在其 `<router-view>` 包裹的所有门户子页面，天然满足“顶栏跨页保留”。无需改动路由（本次不新增 `/announcements`）。
- **首页按钮**：`@click="router.push('/')"`，用 `route.path === '/'` 控制激活样式（文字/图标转 `text-primary`），与现 Logo 行为一致但显式可见。
- **公告按钮**：`@click` 调用 `ElMessage.info('公告页面开发中，敬请期待')`，不导航。预留后续接 `/announcements` 子路由的位置（规则文档中注明）。
- **图标与样式**：首页用 `Home`、公告用 `Megaphone`（lucide-vue-next）；按钮样式对齐现有顶栏——`text-ink-sub hover:bg-gray-50 rounded-lg`，激活态用 `text-primary`，避免引入新视觉语言。

## 实现要点

- 复用现有 Tailwind 令牌：`text-ink-sub(#5E6D82)`、`hover:bg-gray-50`、`text-primary(#5B8FF9)`，与现有用户菜单 hover 风格一致。
- 顶栏结构：Logo（左）→ 新增 nav 按钮组（左，紧跟 Logo）→ 用户菜单（右）。保持 `justify-between` 布局不变。
- 仅新增 `import { ElMessage } from 'element-plus'`（同文件已用 `ElMessageBox`，同一包，零新增依赖）。
- 规则文件作为纯文档，不参与编译，风险为 0；内容明确指出默认值与例外条件。

## 架构设计

- `PortalLayout.vue` 是 `path: '/'` 的布局组件，通过 `<router-view />` 渲染所有门户子页面；顶栏位于布局层，故一次修改即对所有门户页面生效，直接落地“保留顶栏”标准。
- 后台 `AdminLayout.vue` 不改动（其已有独立侧边栏与“返回门户首页”，仅作为规则参照）。

## 目录结构

```
frontend/src/layout/
└── PortalLayout.vue        # [MODIFY] 顶栏新增「首页」「公告」按钮：含图标、激活态样式、首页跳转与公告 toast 逻辑
.codebuddy/rules/
└── 门户顶栏规范.md          # [NEW] 规则文档：门户页面默认沿用 PortalLayout 顶栏并保留首页/公告快速跳转入口，除非单次需求另有说明
```

## 设计说明

在现有门户顶栏（白底、h-16、底部细阴影）左侧 Logo 之后，插入一组导航按钮「首页」「公告」。按钮采用与现有用户菜单一致的轻交互风格：默认 `text-ink-sub` 灰字，hover 出现 `bg-gray-50` 浅灰底与圆角，激活态（当前在首页）文字与图标转为品牌主色 `#5B8FF9`。图标 16px、文字 14px，与右侧用户昵称字号协调，整体保持克制、对齐、无新增视觉噪音。