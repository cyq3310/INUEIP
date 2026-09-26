# 2026-09-23

## [15:30] 功能: 后台应用链接「图标」字段改为可视化图标选择器

- 背景：用户反馈 `el-input__wrapper` 点击不弹出图标面板（实为纯文本输入 + 原生 datalist 的既有设计），右侧 `bg-[#F0F4FD]` 方块被误认为按钮（实为只读预览）。
- 改动：
  - 新增 `frontend/src/config/icon-options.ts`（115 个办公/协作场景精选 lucide 图标，含中文标签，并入原 12 个 iconHints）
  - 新增 `frontend/src/components/IconPicker.vue`（el-input 手输 + 可点预览按钮 + el-dialog 网格面板 + 搜索 + 选中高亮 + 取消回滚）
  - 新增 `frontend/scripts/verify-icon-options.mjs`（运行时 import lucide-vue-next 校验图标名，防静默 fallback）
  - 修改 `frontend/src/views/admin/AppLinks.vue`（图标字段替换为 `<IconPicker v-model="form.icon" />`，删除 iconHints 与 datalist）
- 决策：
  - 面板用 `el-dialog + append-to-body`（el-popover 在 el-form-item 内易被裁切）
  - 渲染复用 `LucideIcon.vue`，禁止新增 `import * as icons`（避免 lucide 命名空间重复打包）
  - 图标名校验以**运行时导出表**为准：lucide 0.577 文件名已改新命名（chart-bar/house/square-kanban/funnel），但旧别名（BarChart3/Home/Grid/Filter）仍导出，按文件名校验会误报
- 验证：`npm run build` 通过（仅既有 chunk 体积警告）；playwright 实跑 admin/Admin@123 → 应用链接 → 新增链接 → 预览块 → 面板 115 项 → 搜索「知识库」命中 1 项 BookOpen → 点选回填 BookOpen → 确定关闭 → 取消回滚 BookOpen → 非法名 NotARealIcon 预览回退 Link，console 无报错
- 环境注意：验证时临时启动过 `php think run -p 8000`（XAMPP php 在 `h:/XAMPP/php/php.exe`，不在 PATH），已停止并清理日志
