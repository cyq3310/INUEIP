---
name: INUWIKI 独立知识库项目（含空间管理）
overview: 开发独立知识库项目 INUWIKI（PHP8.2+ThinkPHP8 后端 / Vue3 前端），账号密码与 RBAC 权限复用 EIP 的 INUEIP 库（只读），内容存 INUEKB 库；自带登录页、会话当天有效。核心新增「空间」维度实现授权可见与统计隔离，支持图文与 100MB-1GB 视频的分片上传、断点续传、异步转码与 X-Accel 流式播放，存储本地磁盘，目标承载 100 人同时操作（峰值并发 60-100）。
design:
  styleKeywords:
    - Fluent Design
    - 企业级门户
    - 柔和渐变
    - 亚克力卡片
    - 分层阴影
    - 微交互
    - 明亮专业
  fontSystem:
    fontFamily: PingFang SC
    heading:
      size: 24px
      weight: 600
    subheading:
      size: 18px
      weight: 500
    body:
      size: 14px
      weight: 400
  colorSystem:
    primary:
      - "#2B5CE6"
      - "#4F7DF3"
      - "#1E3FA8"
    background:
      - "#F5F7FB"
      - "#FFFFFF"
      - "#1A1F2B"
    text:
      - "#1F2430"
      - "#5A6478"
      - "#FFFFFF"
    functional:
      - "#16A34A"
      - "#E5484D"
      - "#F5A524"
      - "#2B5CE6"
todos:
  - id: design-assets
    content: 用 [subagent:吟游廊EIP] 修订原型四页（顶栏改首页/WIKI、加空间切换器、编辑页改设置/正文两页签、新增空间管理页）并重写 INUWIKI/docs/design-plan.md，重新截图出图
    status: completed
  - id: backend-skeleton
    content: 用 [subagent:code-explorer] 核对 EIP 约定后初始化 INUWIKI 后端：ThinkPHP8 骨架、双库连接、登录页鉴权、RBAC 权限读取
    status: completed
    dependencies:
      - design-assets
  - id: space-module
    content: 实现空间与分类：kb_space/kb_space_member 迁移、角色加个人双重授权、可见空间过滤与统计口径A
    status: completed
    dependencies:
      - backend-skeleton
  - id: article-module
    content: 实现文章与权限：CRUD、view_scope/edit_mode、kb_article_editor 授权、正文插入节点与 Purifier 白名单
    status: completed
    dependencies:
      - space-module
  - id: media-pipeline
    content: 实现分片上传四接口与续传、finfo MIME 嗅探、流式合并、FFmpeg 异步转码队列与 X-Accel 流式播放
    status: completed
    dependencies:
      - article-module
  - id: frontend-impl
    content: 用 [subagent:吟游廊EIP] 实现前端门户与后台页面、空间切换器、分片上传组件与播放器、正文渲染器
    status: completed
    dependencies:
      - article-module
      - media-pipeline
  - id: deploy-verify
    content: 用 [skill:编程专家.Skill] 编写 Nginx/PHP-FPM/OPcache/Redis/Supervisor 部署配置并执行 60/80/100 并发压测产出验收报告
    status: completed
    dependencies:
      - frontend-impl
---

## 产品概述

新建独立知识库项目 **INUWIKI**（`h:/XAMPP/htdocs/INUWIKI/`），账号密码与权限管理复用现有 EIP（INUEIP）的用户库与 RBAC，知识库自带登录页（可不经过 EIP 直接使用），支持图文与 100MB–1GB 大视频的发布、检索与在线拖拽播放。核心新增「**空间**」维度：可为公司高管等敏感内容建立受限空间，仅授权人可见其分类页签与内容，且全站统计中不体现。目标承载 100 人同时操作（峰值并发 60–100）。

## 核心功能

### 一、账号与权限（复用 EIP）

- 用户在 EIP 后台统一配置，密码只维护一份；INUWIKI 自带登录页，直查 `inue_user` 校验（bcrypt）
- 角色与权限码从 EIP 的 RBAC 五表读取（只读），`kb:*` 权限点注册进 `inue_permission`，管理员在 EIP 后台分配
- 会话**当天有效**（至当日 24:00），无滑动续期；账号禁用即时生效

### 二、空间管理（新增维度）

- 空间分「公开 / 受限」两类；受限空间按**角色 + 个人**双重授权（可查看 / 可编辑 / 可管理）
- **未授权完全隐藏**：空间本身、其下分类页签、文章、搜索结果、相关推荐均不可见
- **统计口径 A**：全站统计（总数、热门、首页数字、推荐）排除受限空间；空间内部仍保留自身浏览统计，授权者可见
- 超管（`super_admin`）绕过全部限制

### 三、知识文章与权限

- 富文本正文（wangEditor + HTMLPurifier）、封面、摘要、草稿/发布/下架、浏览量
- 单篇可见范围（公开 / 空间内 / 指定范围）与可编辑范围（**所有人可改** / **指定人·角色可改**），授权粒度为**角色 + 个人**
- 默认公开空间人人可创建（wiki 共建），受限空间仅授权成员可写

### 四、视频与附件

- 100MB–1GB 视频分片上传（5MB/片）、断点续传、服务端异步转码（H.264/AAC + faststart + 720p + 封面帧）
- 视频/附件可在**正文任意段落光标处插入**，同时文末保留统一附件列表
- 在线拖拽播放（HTTP Range 206）

### 五、视觉与交互

- 顶栏精简为「首页 / WIKI」
- 门户端：空间切换器 + 分类树 + 卡片列表 + 详情页（播放器 + 目录 + 正文）
- 后台：空间管理、文章表格、文章编辑分「设置」「正文」两个页签，正文编辑区占满整行，视频与附件面板置于编辑器下方

## 技术栈选型

| 层 | 选型 | 说明 |
| --- | --- | --- |
| 后端 | PHP 8.2 + ThinkPHP 8.1 | 与 EIP 同构，可直接复用其 service/middleware 写法 |
| 前端 | Vue3 + Vite5 + Element Plus + Pinia + Tailwind 3 | 沿用 EIP 既有栈 |
| Web/运行时 | Nginx 1.24 + PHP-FPM 8.2（Ubuntu 22.04） | 替换 XAMPP/Apache mod_php |
| 字节码缓存 | OPcache（当前实测 `off`） | 决定性性能项 |
| 缓存/队列 | Redis（phpredis）+ `topthink/think-queue` | Supervisor 守护 |
| 数据库 | MySQL：`INUEIP`（只读，用户/权限）+ `INUEKB`（读写，内容） | 双连接 |
| 存储 | 本地磁盘 `/data/inuwiki` | 沿用 EIP「相对路径 + 前缀」约定 |
| 转码 | FFmpeg（apt） | 队列异步 |
| 图片处理 | `intervention/image` ^3（Imagick 优先，GD 回退） | 缩略图/封面 |
| 播放器 | 原生 `<video>` | 不引入 video.js，避免打包膨胀 |


## 实现方案

### 一、容量结论

现状（XAMPP + opcache off + 同步阻塞）撑不住 60–100 并发；改造后充足。瓶颈不在 ThinkPHP 而在运行时与视频传输模型。

| 环节 | 改造动作 | 评估 |
| --- | --- | --- |
| PHP 执行 | 开启 OPcache（80–150ms → 15–40ms） | `pm.max_children=80`，余量充足 |
| 并发模型 | PHP-FPM 进程池替换 Apache mod_php | 充足 |
| 数据库 | `trigger_sql=false`、`fields_cache=true` | 充足 |
| 缓存 | file → Redis | 充足 |
| **大视频传输** | **`X-Accel-Redirect` + Nginx `internal` + `sendfile`** | **唯一决定性设计**，PHP 不搬运字节 |
| 上传 | 分片 5MB → `post_max_size=10M` 即可 | 无需放开到 1GB |


### 二、关键设计决策

1. **视频字节绝不经过 PHP**：鉴权后返回 `X-Accel-Redirect`，Nginx 静态文件原生支持 Range(206)，拖拽播放天然可用。
2. **分片上传规避 PHP 上传限制**，同时消除 EIP 已有的"超 `post_max_size` 丢弃整个请求体"风险（`Skill.php:316-317` 注释已暴露）。
3. **续传不用浏览器全量哈希**：`/init` 服务端下发 `uploadId`，前端用 `文件名+大小+lastModified` 作 localStorage 键，`/check` 扫描分片目录返回已传索引。
4. **合并与转码异步**：流式 `fopen/fread(8192)/fwrite` 拼接，禁 `file_get_contents`；FFmpeg 入队，状态机 `pending→merging→transcoding→ready/failed`。
5. **空间权限前置过滤**：所有列表/搜索/统计/推荐查询以"可见空间集合"为作用域，避免逐条鉴权。
6. **配置只写 `backend/config/myconfig.php`**，遵守统一配置规范；`database`/`jwt` 禁止进 `frontend_public.keys`。

### 三、系统架构

```mermaid
flowchart TB
    subgraph Client["浏览器 (Vue3 SPA)"]
        A1["门户: 空间切换 + 分类 + 列表/详情"]
        A2["后台: 设置/正文 两页签编辑"]
        A3["分片上传器 + 原生 video 播放器"]
    end
    subgraph Nginx["Nginx 单域名按路径分发"]
        N1["/ 与 /api → INUEIP"]
        N2["/kb 与 /kbapi → INUWIKI"]
        N3["location /internal-media (internal + sendfile, Range 206)"]
    end
    subgraph PHP["INUWIKI PHP-FPM (pm.max_children=80)"]
        P1["AuthController 登录页鉴权"]
        P2["SpaceService 空间/成员/可见域"]
        P3["ArticleService 文章 + 权限判定"]
        P4["UploadService init/check/chunk/complete"]
        P5["MediaController 鉴权→X-Accel-Redirect"]
    end
    subgraph Async["异步层"]
        Q[("Redis cache/queue")]
        W["Supervisor: queue:work"]
        F["FFmpeg 转码 + 封面帧"]
    end
    subgraph Data[("数据层")]
        D1[("INUEIP 只读: inue_user 等 RBAC 五表")]
        D2[("INUEKB 读写: kb_* 六表")]
        D3[("本地磁盘 /data/inuwiki")]
    end
    A1 --> N2 --> P2
    A2 --> N2 --> P4
    A3 --> N3
    P5 -->|"X-Accel-Redirect"| N3
    P1 --> D1
    P2 --> D1
    P2 --> D2
    P3 --> D2
    P4 --> D3
    P4 -->|"入队"| Q
    Q --> W --> F --> D3
```

### 四、数据模型（INUEKB 六表）

| 表 | 关键字段 |
| --- | --- |
| `kb_space` | id, name, code, description, cover, **is_restricted(0公开/1受限)**, status, sort, created_by, created_at, updated_at |
| `kb_space_member` | id, space_id, **subject_type(user\ | role)**, subject_id, can_view, can_edit, can_manage, created_at |
| `kb_category` | id, **space_id**, parent_id, name, sort, status, created_at, updated_at |
| `kb_article` | id, **space_id**, category_id, title, summary, cover, content(LONGTEXT), status(draft/published/offline), **view_scope(public\ | space\ | restricted)**, **edit_mode(open\ | restricted)**, view_count, author_id, published_at, created_at, updated_at |
| `kb_article_editor` | id, article_id, **subject_type(user\ | role)**, subject_id |
| `kb_attachment` | id, article_id, type(image/video/file), original_name, path, thumb_path, mime, size, duration, width, height, sha256, **status(pending/merging/transcoding/ready/failed)**, fail_reason, created_at |
| `kb_upload` | id, upload_id(唯一索引), user_id, filename, size, chunk_size, chunk_total, status, attachment_id, created_at, updated_at |


> INUWIKI **不建** user/role/permission 表。

### 五、权限判定顺序

```
超管(super_admin) → 空间管理员(can_manage) → 空间成员(can_edit)
  → edit_mode=open 且具 kb:article:update
  → 命中 kb_article_editor（user 直配 或 用户所属 role）
```

### 六、分片上传 API 契约

| 接口 | 方法 | 说明 |
| --- | --- | --- |
| `/kbapi/auth/login` | POST | 账密直查 `inue_user` 校验，签发当日有效 JWT |
| `/kbapi/spaces` | GET | 仅返回**可见空间**（未授权完全不返回） |
| `/kbapi/admin/kb/uploads/init` | POST | 返回 `uploadId` |
| `/kbapi/admin/kb/uploads/check` | GET | 返回 `uploaded:[0,1,2…]` |
| `/kbapi/admin/kb/uploads/chunk` | POST | 单片 5MB → `_tmp/{uploadId}/{index}.part` |
| `/kbapi/admin/kb/uploads/complete` | POST | 校验齐全 → 建 attachment(pending) → **入队**，立即返回 |
| `/kbapi/kb/articles/:id/media/:attachmentId` | GET | 鉴权 → `X-Accel-Redirect: /internal-media/…` |


### 七、目录结构

```
h:/XAMPP/htdocs/INUWIKI/                        # 独立项目（INUEIP 源码不动）
├── backend/                                    # [NEW] ThinkPHP 8 后端
│   ├── composer.json                           # [NEW] topthink/framework、think-orm、think-queue、think-migration、firebase/php-jwt、intervention/image、ezyang/htmlpurifier
│   ├── config/
│   │   ├── myconfig.php                        # [NEW] 唯一配置源：upload / knowledge / ffmpeg / media / jwt / database 双连接
│   │   ├── database.php                        # [NEW] 两连接：eip(只读, INUEIP) + kb(读写, INUEKB)
│   │   ├── cache.php / session.php             # [NEW] 默认 redis
│   │   └── queue.php                           # [NEW] think-queue redis 驱动
│   ├── database/migrations/
│   │   ├── 2026xxxx_create_kb_space_tables.php # [NEW] kb_space + kb_space_member
│   │   ├── 2026xxxx_create_kb_content_tables.php# [NEW] kb_category/article/attachment/upload + kb_article_editor
│   │   └── 2026xxxx_seed_kb_permissions.php    # [NEW] 向 INUEIP.inue_permission 插入 kb:* 权限点（数据，非源码）
│   ├── app/
│   │   ├── model/                              # [NEW] Space/SpaceMember/Category/Article/ArticleEditor/Attachment/Upload
│   │   ├── service/
│   │   │   ├── AuthService.php                 # [NEW] 直查 inue_user + password_verify + 当日有效 JWT + 失败锁定
│   │   │   ├── RbacService.php                 # [NEW] 只读 EIP RBAC 五表，buildProfile（照搬 EIP AuthService:77-107）
│   │   │   ├── SpaceService.php                # [NEW] 空间 CRUD、成员授权、可见空间集合、统计口径A
│   │   │   ├── ArticleService.php              # [NEW] 文章 CRUD、状态流转、权限判定、浏览量
│   │   │   ├── UploadService.php               # [NEW] 分片四接口 + finfo MIME 嗅探 + realpath 越界校验
│   │   │   ├── MediaService.php                # [NEW] ffprobe/FFmpeg + X-Accel-Redirect 路径拼装
│   │   │   └── PurifierService.php             # [NEW] 复用 EIP 思路，白名单放行 data-kb-video / data-kb-file
│   │   ├── middleware/{AuthCheck,PermissionCheck}.php  # [NEW] 照搬 EIP 同文件中11-21 判定逻辑
│   │   ├── job/KbProcessJob.php                # [NEW] 流式合并 → 转码 → 封面 → 更新状态
│   │   ├── command/KbGc.php                    # [NEW] 清理 _tmp 与孤儿文件（照 SkillGc.php）
│   │   ├── controller/{Auth,Space,Article,admin/Space,admin/Article,admin/Upload,Media}.php # [NEW]
│   │   ├── common.php                          # [NEW] upload_dir()/upload_prefix()/move_to_trash()/gc_trash() 同名 helper
│   │   └── route/app.php                       # [NEW] 路由 + PermissionCheck 权限点
│   └── public/index.php                        # [NEW]
├── frontend/                                   # [NEW] Vue3 + Vite5
│   └── src/
│       ├── api/{space,article,upload}.ts       # [NEW]
│       ├── components/
│       │   ├── KbSpaceSwitcher.vue             # [NEW] 空间切换器
│       │   ├── KbChunkUploader.vue             # [NEW] 分片上传（并发3、进度、暂停、续传）
│       │   ├── KbVideoPlayer.vue               # [NEW] 原生 video + Range 拖拽
│       │   └── KbContentRenderer.vue           # [NEW] 正文渲染，替换占位节点为播放器/附件卡
│       └── views/
│           ├── portal/{WikiList,WikiDetail}.vue# [NEW]
│           ├── admin/{SpaceManage,WikiList,WikiEdit}.vue # [NEW] 编辑页两页签
│           └── Login.vue                       # [NEW] 知识库独立登录页
├── deploy/                                     # [NEW]
│   ├── nginx/inuwiki.conf                      # [NEW] 按路径分发、client_max_body_size 12m、/internal-media internal+sendfile、uploads 禁 PHP + nosniff
│   ├── php-fpm/inuwiki.conf                    # [NEW] pm.max_children=80、pm.max_requests=1000
│   ├── php/opcache.ini                         # [NEW] enable=1、memory_consumption=256、validate_timestamps=0
│   ├── supervisor/kb-worker.conf               # [NEW] 守护 queue:work，numprocs=2
│   └── README.md                               # [NEW] 部署步骤 + 回滚预案
├── prototype/                                  # [MODIFY] 原型按最新要求修订
│   ├── assets/prototype.css                    # [MODIFY] 设计系统（已存在）
│   ├── portal-list.html / portal-detail.html   # [MODIFY] 顶栏改「首页/WIKI」、加空间切换器
│   ├── admin-list.html / admin-edit.html       # [MODIFY] 编辑页改「设置/正文」两页签
│   └── screenshots/*.png                       # [MODIFY] 重新出图
└── docs/design-plan.md                         # [MODIFY] 重写：空间模型、权限矩阵、统计口径A、API 契约
```

### 八、关键代码结构

```
// backend/config/myconfig.php（环境配置唯一来源）
'upload'    => ['driver'=>'local','root'=>'','url_prefix'=>'/uploads','max_size'=>...],  // 与 EIP 同构
'knowledge' => [
    'upload_prefix'   => '/uploads/knowledge',   // 与 EIP announcement.upload_prefix 同约定
    'remote_base_url' => '',                     // 预留 CDN
    'chunk_size'      => 5 * 1024 * 1024,        // 与 nginx client_max_body_size 联动
    'image_max'       => 10 * 1024 * 1024,
    'video_max'       => 1024 * 1024 * 1024,     // 单视频上限 1GB
    'image_ext'       => ['jpg','jpeg','png','webp','gif'],
    'video_ext'       => ['mp4','mov','mkv','avi','webm'],
    'mime_sniff'      => true,                   // 用户确认新增：finfo 真实类型校验
],
'ffmpeg' => ['bin'=>'/usr/bin/ffmpeg','preset'=>'veryfast','height'=>720],
'media'  => ['internal_prefix'=>'/internal-media'],   // 必须与 nginx internal location 严格一致
```

```
// 权限核心契约（仅签名）
interface SpaceAccessResolver {
    /** 返回当前用户可见空间 ID 集合（超管返回全部，受限空间按 role+user 授权求并集） */
    public function visibleSpaceIds(int $userId, array $roleCodes, bool $isSuperAdmin): array;
}
interface ArticleAccessChecker {
    /** 依次判定 view / edit 权限；未授权抛 403（对列表查询则直接过滤掉） */
    public function canView(int $articleId, int $userId): bool;
    public function canEdit(int $articleId, int $userId): bool;
}
```

## 实现要点（防回归）

- **`X-Accel-Redirect` 路径必须与 Nginx `internal` location 严格对齐**，PHP 侧用 `realpath` 校验文件位于存储根内，杜绝目录穿越（复用 EIP `common.php:117 move_to_trash()` 的越界校验写法）。
- **空间过滤必须前置**：列表/搜索/统计/推荐一律先取"可见空间集合"再查，避免逐条鉴权导致 N+1 与越权泄漏；统计口径 A 要求全站聚合 SQL 排除 `is_restricted=1` 且未授权的空间。
- **未授权 = 完全隐藏**，接口不得返回"存在但无权限"的提示（避免信息泄漏）。
- **合并分片流式**，`memory_limit` 保持 512M，峰值不应超过数十 MB。
- **转码禁止同步**：`complete` 只入队立即返回；FFmpeg `proc_open` + 超时，失败写 `fail_reason` 支持重试；并发限制 1–2 路防 CPU 打满。
- **MIME 嗅探**：在 EIP 现有"扩展名+大小"之上补 `finfo`，扩展名与真实类型双白名单比对，不一致即拒。
- **HTMLPurifier 必须白名单放行** `div[data-kb-video]`、`a[data-kb-file]`，否则正文插入的视频/附件节点会被 XSS 过滤掉。
- **uploads 目录禁 PHP 执行**（Nginx `location ~ \.php$ { deny all; }`）+ `X-Content-Type-Options: nosniff`。
- **OPcache `validate_timestamps=0` 后发版必须 `php-fpm reload`**，写进部署手册。
- **EIP 源码零改动**；唯一涉及 EIP 的是通过迁移脚本向 `inue_permission` 插入 `kb:*` 权限点（数据，非源码）。
- **压测验收**：60/80/100 三档，API P95 < 500ms、错误率 < 0.5%；30 路并发播放验证 PHP-FPM worker 不被占用（观察 `pm.status_path`）。

## 设计风格

延续 INUEIP 现有 Element Plus 企业级门户风格，采用 **Fluent Design** 视觉语言：柔和渐变、亚克力质感卡片、清晰分层阴影、克制的微交互。整体明亮专业；在视频/上传这类"重操作"区域用更强的层次与状态反馈降低焦虑感。

## 页面规划（5 屏）

### 1. 门户 · WIKI 列表页

- **顶部导航栏**：品牌「吟游廊 WIKI」，导航仅两项 **首页 / WIKI**（去掉 AI 技能、公告），右侧搜索框与用户头像
- **空间切换区**：左侧顶部横向空间切换器（Segmented 形态），仅展示当前用户可见空间；受限空间带锁形图标
- **分类导航区**：空间切换器下方竖向分类树，卡片式，选中态左侧色条 + 浅色底
- **知识卡片瀑布区**：封面 16:9，视频类叠加半透明播放三角与时长角标，标题两行截断、摘要、分类标签、浏览量与更新时间；hover 封面轻微放大 + 卡片抬升阴影
- **分页与空状态**：Element Plus 分页；无数据展示插画式空状态

### 2. 门户 · WIKI 详情页

- **顶部导航栏**：面包屑（WIKI / 空间 / 分类 / 标题），右侧收藏/分享
- **视频播放区**：16:9 深色播放器，圆角卡片包裹；自定义控制条（播放/暂停、带缓冲态进度条、倍速、画质、全屏）；未就绪显示"转码中"骨架屏
- **正文阅读区**：富文本正文，其中按段落内嵌已插入的视频与附件卡片；图片点击灯箱放大
- **目录与附件**：左侧可折叠目录锚点（跟随滚动高亮）；底部文末统一附件列表
- **底部信息栏**：作者、发布时间、浏览量、上下篇切换、相关推荐横滑列表（相关推荐同样受可见空间约束）

### 3. 后台 · WIKI 管理页

- **顶部导航栏**：后台统一导航 + 环境与账号信息
- **空间与分类面板**：左侧上方空间列表（含"新建空间"、受限标记），下方为当前空间分类树（新增/重命名/拖拽排序/启停）
- **文章表格**：封面缩略图、标题、分类、状态标签（草稿灰/已发布绿/已下架橙）、可见范围标签、浏览量、更新时间；工具栏含搜索框 + 空间筛选 + 分类筛选 + 状态筛选 + "新建文章"主按钮
- **操作列与统计条**：行内"编辑/上架下架/删除"（删除二次确认）；页脚展示存储已用容量与转码队列状态

### 4. 后台 · 文章编辑页（两页签）

- **顶部导航栏**：面包屑返回 + 右侧"保存草稿 / 发布"双按钮（主次分明）
- **页签切换**：「设置」与「正文」两个页签，默认进入「设置」
- **「设置」页签**：文章标题、所属空间 + 分类、封面（拖拽上传 + 预览 + 裁剪）、摘要、发布设置（状态/发布时间）、**可见范围**、**可编辑范围**（所有人可改 / 指定人·角色，角色+人双选控件）
- **「正文」页签**：富文本编辑器**占满整行宽度**（解决"只占一小部分"问题），工具栏新增「插入视频」「插入附件」；**编辑器下方依次是「视频」上传面板与「附件」区域**

### 5. 后台 · 空间管理页

- 空间列表卡片（名称、编码、类型公开/受限、成员数、文章数）
- 新建/编辑空间抽屉：基础信息 + 受限开关 + **成员授权**（按角色批量授权 + 按个人单独授权，可分别勾选查看/编辑/管理）

## 响应式与交互

- 桌面优先（1280–1920px），≤1024px 时空间与分类树折叠为抽屉；播放器宽度自适应保持 16:9
- 微交互：卡片 hover 上浮、按钮涟漪、进度条平滑过渡、骨架屏渐显；状态切换 200–300ms 缓动
- 上传全程可中断可恢复，失败在面板内联提示原因，不使用遮挡式弹窗

## Agent Extensions

### SubAgent

- **吟游廊EIP**
- Purpose: 作为熟悉本项目的产品经理兼资深全栈开发，负责 INUWIKI 端到端实现（ThinkPHP 分层代码、Vue3 页面、空间权限、分片上传与视频链路）
- Expected outcome: 产出符合 EIP 既有 controller/service/model 分层与 RBAC 约定的可运行代码，复用 `common.php` 与 `myconfig.php` 现有约定

- **code-explorer**
- Purpose: 实施前精确定位 EIP 约定所在位置与影响面（`AuthService` 取权限写法、`PurifierService` 白名单配置、`upload_dir`/`move_to_trash` 实现、`SkillGc.php` 命令范式）
- Expected outcome: 给出文件:行号 + 原文证据，确保沿用一致且不误改 INUEIP 既有功能

### Skill

- **编程专家.Skill**
- Purpose: 提供工程纪律保障——验收口径、依赖引入门禁、批量修改防线、生产就绪检查、清单化验证与复盘
- Expected outcome: 每个 Wave 通过清单化自审与真人功能验证，压测有实测证据而非静态断言，未验证项强制披露