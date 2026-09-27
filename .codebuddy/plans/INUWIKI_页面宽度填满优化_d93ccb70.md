---
name: INUWIKI 页面宽度填满优化
overview: 将知识库各页面从「1160/1440px 居中留白」改为「1920px 封顶铺满」（与 INUEIP 一致），卡片网格改为自适应列数，详情页正文文字限宽居中、媒体铺满，并修复后台页双重内边距导致的左右不对称。
todos:
  - id: layout-tokens
    content: 修改 design.css：新增 1920px 令牌，page-max/page-wide 统一铺满，topbar 内边距对齐内容列
    status: completed
  - id: grid-adaptive
    content: 将 .grid 改为 auto-fill 自适应列数，并调整 sidebar 与 form-grid 侧栏宽度
    status: completed
    dependencies:
      - layout-tokens
  - id: detail-readability
    content: 详情页正文/标题限宽 880px 居中，播放器上限 1200px，媒体块保持铺满
    status: completed
    dependencies:
      - layout-tokens
  - id: admin-padding
    content: 修复 AdminLayout 双重内边距，移除 SpaceManage 内联 4 列覆盖
    status: completed
  - id: build-verify
    content: 运行 npm run build 并用 [skill:playwright-cli] 在 1920 宽截图验证无留白
    status: completed
    dependencies:
      - layout-tokens
      - grid-adaptive
      - detail-readability
      - admin-padding
---

## 产品概述

INUWIKI 知识库前端当前所有页面内容区被限制在 1160px / 1440px 并水平居中，在 1920 宽屏上左右各留出大量空白。本次优化将内容区改为「1920px 封顶铺满」，与 INUEIP 页面的观感一致，同时解决拉宽后卡片过大、正文过长、后台页左右内边距不对称等连带问题。

## 核心需求

- 门户页、详情页、后台知识管理、编辑页、空间管理五个页面的内容区铺满到 1920px 封顶（1920 及以下分辨率完全铺满，超宽屏不再拉伸），与 INUEIP 一致
- 顶栏背景保持通栏，但内部元素与 1920 内容列对齐，超宽屏不脱节
- 知识卡片网格改为自适应列数：容器越宽列数越多（1920 屏约 5-6 列），卡片尺寸保持精致，不因拉宽而变得又宽又高
- 详情页整体骨架铺满，但正文文字区限制在约 880px 并居中（一行 40-50 字，长文阅读不累）；播放器与附件区保持铺满
- 修复后台页面左右内边距 56px（双重 padding）与门户页 28px 不一致的问题

## 不在本次范围

- 登录页（400px 居中卡片为登录页常规设计，保持不变）
- 设计令牌（配色、圆角、阴影、字体）全部保持不变，仅调整布局尺寸
- 原型目录 `INUWIKI/prototype/` 为视觉稿，不改动

## 技术栈

- 现有项目：Vue 3.5 + TypeScript + Vite 5 + Tailwind 3.4 + Element Plus
- 全局样式集中在 `frontend/src/styles/design.css`（全局引入，非 scoped，页面直接复用原型类名）
- 构建校验：`npm run build`（`vue-tsc -b && vite build`）

## 根因定位（已核实）

1. `frontend/src/styles/design.css` 第 168-175 行：`.page-max { max-width: 1160px; margin: 0 auto }` / `.page-wide { max-width: 1440px; margin: 0 auto }`。INUEIP 对应写法为 `INUEIP/frontend/src/layout/PortalLayout.vue:53` 的 `max-w-[1920px] px-8`
2. `design.css` 第 325-329 行：`.grid { grid-template-columns: repeat(3, 1fr) }` 写死 3 列，容器拉宽后 16:9 封面会被撑得过高
3. `AdminLayout.vue:87` 的 `<main class="px-7 pb-10 pt-6">` 与子页面自带 28px 横向 padding 叠加，后台页左右实际 56px

## 实现方案

### 1. 统一页面容器宽度（`design.css`）

新增布局令牌 `--page-max: 1920px`，`.page-max` 与 `.page-wide` 均改为 `max-width: var(--page-max); margin: 0 auto;`。保留两个类名以兼容现有 5 个页面的调用，避免大范围改动类名。

### 2. 顶栏与内容列对齐（`design.css`）

`.topbar` 背景保持通栏，横向内边距改为：
`padding-inline: calc(28px + max(0px, (100% - var(--page-max)) / 2))`
效果：1920 屏为 28px（与内容列左边缘一致），2560 屏为 348px（与居中内容列对齐），小于 1920 时恒为 28px。无需改动 `PortalLayout.vue` 结构。

### 3. 卡片网格自适应（`design.css`）

`.grid` 改为 `grid-template-columns: repeat(auto-fill, minmax(min(280px, 100%), 1fr))`，gap 保持 18px。`min(280px, 100%)` 保证窄屏不溢出。

### 4. 详情页正文限宽（`design.css`）

正文纯文字块由 `KbContentRenderer.vue:74` 输出为 `.article-body`，视频为 `.kb-player`、附件为 `.kb-file-card`，三者是并列的兄弟节点。因此：

- `.article-body { max-width: 880px; margin-inline: auto; }` —— 仅约束文字，媒体块自然铺满
- 同步给 `.article h2`、`.article-meta` 加同样的 880px 居中约束，保证标题、元信息、正文左边缘对齐，不与正文错位
- `.kb-player` 增加 `max-width: 1200px; margin-inline: auto`：1920 屏主列约 1572px，16:9 播放器会高达 884px（超过一屏），需要上限保护

### 5. 后台布局修正

- `AdminLayout.vue`：`<main class="px-7 pb-10 pt-6">` 去掉 `px-7`，横向 padding 交由子页面（`.layout` / `.page-max` 各自的 28px）负责，门户与后台统一为 28px
- `SpaceManage.vue:263` 移除内联 `style="grid-template-columns: repeat(4, 1fr)"`，否则会覆盖新的自适应网格
- `design.css`：`.sidebar` 232px → 248px、`.form-grid` 右栏 320px → 340px，让宽屏下侧栏比例不显得过窄

## 实施要点（防回归）

- 只动尺寸类 CSS，不碰颜色/圆角/阴影令牌，避免与原型视觉稿脱节
- `.article-body` 为全局类且在子组件中渲染，样式必须写在 `design.css`（全局），写在 `WikiDetail.vue` 的 `<style scoped>` 中不生效
- 改动后用 `npm run build` 做类型检查 + 构建门禁（上次基线：通过，exitCode 0，仅有 chunk >500kB 的既有告警）
- 验证重点：1920 宽下左右无留白、卡片列数为 5-6 且不过高、详情页正文约 880px 居中而播放器铺满、后台页与门户页左右边距一致

## 目录结构

```
INUWIKI/frontend/src/
├── styles/
│   └── design.css                    # [MODIFY] 核心改动。新增 --page-max: 1920px 令牌；.page-max/.page-wide 统一 1920px；.grid 改 auto-fill 自适应列；.topbar 内边距对齐内容列；.article-body/.article h2/.article-meta 限宽 880px 居中；.kb-player 上限 1200px；.sidebar 248px；.form-grid 右栏 340px
├── layout/
│   └── AdminLayout.vue               # [MODIFY] <main> 移除 px-7，消除与子页面 28px 的双重内边距
├── views/
│   ├── portal/
│   │   └── WikiList.vue              # [MODIFY] 复核 .layout + .page-max 在新宽度下的侧栏/网格比例
│   ├── portal/
│   │   └── WikiDetail.vue            # [MODIFY] 复核 detail-grid（1fr 268px）主列拉宽后正文与 TOC 的排布
│   └── admin/
│       ├── WikiList.vue              # [MODIFY] 复核 .layout + .page-wide（现为 1920）
│       ├── WikiEdit.vue              # [MODIFY] 复核 .form-grid 在宽屏下的表单列宽
│       └── SpaceManage.vue           # [MODIFY] 移除 .grid 上的内联 repeat(4, 1fr)，改用自适应网格
└── components/
    └── KbContentRenderer.vue         # 只读复核：.article-body / .kb-player / .kb-file-card 为并列兄弟节点，限宽样式只需作用于文字块
```

## Agent Extensions

### Skill

- **playwright-cli**
- Purpose: 在 1920 视口下打开门户首页与详情页截图，确认左右无留白、卡片列数与正文宽度符合预期
- Expected outcome: 产出截图证据，验证「铺满」目标达成且未出现播放器过高、正文过长等副作用