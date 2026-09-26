# 2026-09-20

## [18:55] 功能: 公告置顶排序 + 置顶操作 + 编辑弹窗扩容 + 正文图片上传归档

- 迁移 `20260920000100_add_announcement_top_fields.php`：announcement 增 `is_top` tinyint、`topped_at` datetime，索引 idx_announcement_top(status,is_top,topped_at)。已 `php think migrate:run` 执行。
- 种子 `AnnouncementTopSeeder.php`（幂等，仿 PortalThemeSeeder）：权限 `announcement:top`(36)、`announcement:upload`(37)，父级 30，自动授予 role_id=1。已执行。
- `admin/Announcement.php`：新增 `top()`（仅 status=1 可置顶，上限 TOP_LIMIT=3，超限 resp_fail）、`upload()`（5MB / jpg,jpeg,png,webp,gif，落 `uploads/announcement/{id}`，无 ID 落 tmp）、`moveTmpImages()`（保存时把 tmp 图片移动到 ID 目录并改写 img src）、`hasAllowedImageSrc()`（正文 img src 必须为 `/uploads/announcement/...`，禁外链）；`offline()` 联动清置顶；列表排序 is_top>topped_at>id。
- `Portal.php home()`：公告排序 `is_top desc, topped_at desc, published_at desc`，field 增加 is_top。
- 路由：`POST announcements/upload`、`POST announcements/:id/top`（分别受 announcement:upload / announcement:top 保护）。
- 前端：`AnnouncementItem` 增 is_top/topped_at；api 增 top/upload；管理页增置顶列与置顶/取消置顶按钮（操作列 200→270）、弹窗 760px→`min(1500px,92vw)`、编辑器 h-64→h-80、wangEditor `MENU_CONF.uploadImage.customUpload` 打通粘贴/拖拽上传；首页置顶项显示「置顶」标记。
- 验证证据：`php -l` 5 文件通过；`npm run build`（vue-tsc）通过；上传→创建带图公告后图片归档到 `uploads/announcement/12/` 且 src 改写；置顶第 4 条被拒（`最多只能置顶 3 条公告`）；取消置顶后名额释放、下架自动清置顶；首页返回顺序 12>1>4（置顶）再按时间。测试数据已清理（公告 12 软删除、图片目录移除）。
- 已知限制：编辑器中直接粘贴带外链图片的 HTML 会在保存时被拒绝（需重新上传）；放弃编辑时已上传的 tmp 图片会残留在 `uploads/announcement/tmp/`；软删除公告的图片目录不自动清理。
