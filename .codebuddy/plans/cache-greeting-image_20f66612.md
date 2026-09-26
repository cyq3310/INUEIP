---
name: cache-greeting-image
overview: 修复"每次回到首页问候区背景图都要重新加载"的问题：后端给 uploads 静态图片加长效 Cache-Control 头（浏览器本地缓存），前端用 Pinia 缓存门户主题配置避免回首页时重复请求与背景闪烁。
todos:
  - id: add-uploads-cache
    content: 新增 backend/public/uploads/.htaccess 设置图片长效缓存头
    status: completed
  - id: add-portal-store
    content: 新建 frontend/src/stores/portal.ts 缓存门户主题配置
    status: completed
  - id: wire-home-store
    content: 改造 Home.vue 由 store 读取主题并消除闪烁
    status: completed
    dependencies:
      - add-portal-store
  - id: verify-cache
    content: 启动 dev 验证回首页图片命中缓存且无闪烁
    status: completed
    dependencies:
      - add-uploads-cache
      - wire-home-store
---

## 用户需求

用户发现每次离开首页再返回时，问候区背景图都会被重新加载一遍，怀疑本地没有做缓存处理。要求修复该问题，让返回首页时问候区背景图不再重复加载。

## 产品概述

门户首页问候区背景图由后端静态托管（public/uploads/portal/...），当前前端每次回首页都重新挂载组件并重发图片请求，且后端静态资源未设置浏览器缓存头，导致图片每次都被重新请求/校验。需要在后端与前端两层补齐缓存，使返回首页时图片直接命中本地缓存、背景不再闪烁。

## 核心特性

- 后端给 uploads 静态图片加长效缓存头（Cache-Control + Expires），浏览器本地缓存问候区背景图，返回首页不再发起新网络请求。
- 前端新增 Pinia 门户缓存 store，保存主题配置；返回首页时同步读取已缓存配置，避免背景“先空白后显示”的闪烁，并省去重复的 /portal/theme 接口请求。
- 问候区背景图在返回首页时直接复用本地缓存渲染，不再“加载一遍”。

## 技术栈

- 前端：Vue 3 + TypeScript + Pinia（沿用现有 frontend/src/stores/user.ts 的 Pinia 模式），无新增依赖。
- 后端：ThinkPHP（PHP），静态资源由 Apache 直出；沿用现有 backend/public/.htaccess 的 Apache 配置方式。
- 开发代理：frontend/vite.config.ts 已把 '/uploads' 代理到后端 127.0.0.1:8000，后端响应头会透传给浏览器。

## 实现方案

采用“后端浏览器缓存 + 前端状态缓存”两层互补策略：

- **后端层（A）**：在 backend/public/uploads/ 新增 .htaccess，针对图片类型设置长效 `Cache-Control: public, max-age=31536000, immutable` 与 `Expires`（mod_expires）。这样问候区背景图首次下载后被浏览器缓存，组件重建时 `<img>` 的 src 不变，浏览器直接命中 disk/memory cache，不再发起网络请求，从根本上解决“图又加载一遍”。
- **前端层（B）**：新增 `usePortalStore`（Pinia）缓存门户主题配置。`Home.vue` 改为：挂载时优先同步读取 store 中已缓存的 theme（回首页时立即可用，无闪烁），仅当 store 为空才调用 `portalApi.theme()` 并写入 store。原有的 `greetingTheme` 局部 ref 改为由 store 派生的 computed，消除“背景空白→出现”的闪烁，并省一次接口往返。

### 关键决策与权衡

- 不采用 `<KeepAlive>` 缓存整个 Home 路由：会改变其他区块（链接/公告）的数据刷新时机，范围过大；本项目用“store 缓存主题 + 浏览器图片缓存”即可达成目标且影响最小。
- 缓存头加 `immutable`：问候图文件名含时间戳且上传即唯一，内容不会变，适合强缓存；若后续需支持“换图即时生效”，可在换图时让后端返回新文件名（当前已是时间戳命名），无需版本号参数。
- 前端仅缓存 theme（聚焦本次问题），store 设计预留可扩展缓存 summary/home 的字段，但不本次实现，避免 YAGNI。

## 实现要点

- `.htaccess` 放置在 `/uploads` 目录内，仅作用于上传资源，不影响 `/api` 与路由转发；XAMPP 默认启用 mod_expires、mod_headers 且 AllowOverride 生效。
- 前端 store 与现有 `useUserStore` 保持一致：`defineStore` + `ref`/`computed`，错误用 `.catch(console.error)`（遵循项目无 try/catch 约定）。
- `Home.vue` 删除原先的 `greetingTheme = ref(null)` 与 onMounted 中对 theme 的单独赋值；改为 `const greetingTheme = computed(() => portalStore.theme?.greeting ?? null)`，onMounted 中 `portalStore.ensureTheme().catch(console.error)`。注意保留原有 summary/home/announcements 的加载逻辑不变。

## 架构设计

- `usePortalStore` 作为门户级单例状态，位于 Pinia，随应用生命周期常驻；`Home.vue` 是其消费者。问候区背景图由 store 的 `theme.greeting.image_path` 驱动 `<img>`。
- 后端静态文件缓存由 Apache 在响应头层面完成，与前端解耦，对开发代理与生产部署均生效。
- 数据流（返回首页）：组件挂载 → computed 读 store（已缓存，同步）→ `<img>` 复用浏览器缓存渲染；无需接口请求、无闪烁。

## 目录结构

```
backend/public/uploads/
└── .htaccess          # [NEW] 对 uploads 下图片设置长效浏览器缓存头（Cache-Control/Expires），消除回首页图片重复请求
frontend/src/stores/
└── portal.ts          # [NEW] usePortalStore（Pinia），缓存门户主题配置，提供 ensureTheme()（缺失才请求）与 theme 状态
frontend/src/views/portal/
└── Home.vue           # [MODIFY] greetingTheme 改为基于 store 的 computed；onMounted 调用 portalStore.ensureTheme()；移除局部 theme ref 与重复赋值
```

## 关键代码结构

```ts
// frontend/src/stores/portal.ts
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { portalApi } from '../api'
import type { PortalThemeConfig } from '../types'

export const usePortalStore = defineStore('portal', () => {
  const theme = ref<Record<string, PortalThemeConfig> | null>(null)

  // 仅当未缓存时才请求接口；已缓存则同步返回，避免回首页闪烁与重复请求
  const ensureTheme = async (): Promise<Record<string, PortalThemeConfig> | null> => {
    if (theme.value) return theme.value
    const res = await portalApi.theme()
    theme.value = res.data
    return theme.value
  }

  return { theme, ensureTheme }
})
```

```
# backend/public/uploads/.htaccess
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType image/jpeg "access plus 1 year"
  ExpiresByType image/png  "access plus 1 year"
  ExpiresByType image/webp "access plus 1 year"
  ExpiresByType image/gif  "access plus 1 year"
</IfModule>
<IfModule mod_headers.c>
  Header set Cache-Control "public, max-age=31536000, immutable"
</IfModule>
```