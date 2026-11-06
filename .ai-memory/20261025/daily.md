# 2026-10-25

## [16:00] Bug修复: 公告文字样式（颜色/背景/字号/字体/行高）发布后不生效

- 根因：HTMLPurifier 白名单无 `span` 与 `style` 属性 → wangEditor 的 `<span style="color:...;font-size:...">` 与 `<p style="line-height:...">` 全被剥
- 修复（PurifierService.php）：HTML.Allowed 为 span/p/div/li/h1-h4/blockquote/a/td 等放行 style；`CSS.AllowedProperties` 限定 color/background-color/font-size/font-family/font-weight/font-style/line-height/text-align/text-decoration/text-indent（lookup 需 key=>true）；HTML.DefinitionRev 升至 2 并清缓存
- 验证：存储保留 style；渲染 computed color/backgroundColor/fontSize/fontFamily/lineHeight 全部生效；注入 `position:fixed`、`background-image:url(javascript:)` 被剥空（CSS 注入拦截有效）

## [15:00] Bug修复: 公告富文本「无序/有序/待办列表」发布后不生效

- 现象：编辑器内列表/待办正常，发布后详情展示无项目符号/编号，待办只剩纯文本
- 取证（浏览器端到端）：编辑器输出 `<ul><li>`/`<ol><li>`/`<div data-w-e-type="todo"><input type="checkbox" disabled>`；净化后 ul/ol/li 保留、todo 被剥成纯文本
- 根因：
  1. HTMLPurifier 白名单无 `div`/`input`，且默认模块不支持 data-*（HTML.Allowed 无法新增未知属性）→ 待办全剥
  2. 展示侧 Tailwind preflight 重置 `ul,ol{list-style:none;padding:0}` → 符号/编号不可见
- 修复：
  - `backend/app/service/PurifierService.php`：`maybeGetRawHTMLDefinition()` 扩展定义（input 限定 Enum#checkbox + checked/disabled、div 限 Enum#todo），HTML.Allowed 增 `div[data-w-e-type],input[type|checked|disabled]`；需配 `HTML.DefinitionID/Rev`，改定义后须清 `backend/runtime/purifier` 缓存
  - `frontend/src/components/AnnouncementDetail.vue`：还原 ul(disc)/ol(decimal)/嵌套(circle/lower-alpha) 样式与缩进，todo flex 布局 + checkbox
- 验证：`npm run build` 通过；浏览器全链路断言（listStyle=disc/decimal、paddingLeft=24px、todoCount=2、checkedCount=1）+ 截图；测试数据已删除
- 关键教训：HTMLPurifier 的 HTML.Allowed 只能白名单已定义属性；新增元素/属性必须走 maybeGetRawHTMLDefinition 且需 DefinitionID，改后清缓存
