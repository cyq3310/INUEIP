# 吟游廊 EIP平台（INUEIP）

企业信息门户（Enterprise Information Portal）：门户首页 + 后台管理系统。

## 技术栈

| 端 | 技术 |
|---|---|
| 后端 | PHP 8.2 + ThinkPHP 8.1（`backend/`） |
| 前端 | Vue3 + Vite 5 + Element Plus + Pinia + Tailwind CSS 3（`frontend/`） |
| 数据库 | MySQL / MariaDB，库名 **INUEIP**，表前缀 `inue_`，utf8mb4 |
| 鉴权 | JWT（firebase/php-jwt），密码 bcrypt，登录失败 5 次锁定 15 分钟 |

## 功能

- **门户首页**：按时段问候、考勤统计卡片（占位接口）、协作与办公、公告与通知、自定义工具
- **核心特色**：「协作与办公」「自定义工具」由管理员在后台配置应用链接（名称/图标/副标题/URL/排序/启停）后才显示，未配置或停用则整块隐藏
- **账号管理**：用户增删改查、启禁用、重置密码、分配角色
- **权限管理**：RBAC（用户-角色-权限），菜单级 + 接口级双层校验
- **公告管理**：富文本（wangEditor）发布/编辑/上架/下架，正文 XSS 白名单过滤（HTMLPurifier）
- **操作日志**：后台写操作留痕（`inue_operation_log`）

## 快速开始

### 1. 数据库

```sql
CREATE DATABASE INUEIP DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

### 2. 后端

```bash
cd backend
cp .env.example .env   # 修改数据库账号与 JWT_SECRET
composer install       # 或使用项目级 tools/composer.phar
php think migrate:run  # 建表（9 张）
php think seed:run     # 初始数据（账号/角色/权限树/示例链接与公告）
php think run -p 8000  # 启动：http://127.0.0.1:8000
```

### 3. 前端

```bash
cd frontend
npm install
npm run dev            # 启动：http://localhost:5173（/api 已代理到 8000）
```

### 4. 初始账号

| 账号 | 密码 | 角色 |
|---|---|---|
| `admin` | `Admin@123` | 超级管理员（全部权限） |
| `zhangsan` | `User@12345` | 普通用户（仅门户首页） |

> 生产部署前请修改默认密码与 `JWT_SECRET`。

## API 概览

统一响应 `{code, msg, data}`，`code=0` 成功；401 未登录，403 无权限。

- `POST /api/auth/login`、`POST /api/auth/logout`、`GET /api/auth/profile`
- `GET /api/portal/summary` 考勤统计（占位，后续对接真实考勤系统只替换此接口数据源）
- `GET /api/portal/home` 门户聚合（启用链接分组 + 已发布公告）
- `GET/POST/PUT/DELETE /api/admin/users` + `POST /api/admin/users/:id/reset-password`
- `GET/POST/PUT/DELETE /api/admin/roles` + `POST /api/admin/roles/:id/permissions`、`GET /api/admin/permissions`
- `GET/POST/PUT/DELETE /api/admin/app-links`（`section=collab|custom` 过滤）
- `GET/POST/PUT/DELETE /api/admin/announcements` + `POST /api/admin/announcements/:id/publish|offline`

## 目录结构

```
INUEIP/
├── backend/    ThinkPHP 8 后端（控制器-服务-模型分层，路由含接口级权限中间件）
├── frontend/   Vue3 前端（门户布局 + 后台布局，路由守卫按权限过滤菜单）
├── docs/       文档
└── tools/      项目级 Composer 等工具
```

## 二期规划

- 考勤统计对接真实打卡/审批系统（替换 `/api/portal/summary` 数据源）
- 头像上传、操作日志查询页、公告附件
- 前端分包优化（wangEditor / lucide 按需加载）
