session-id: 20260913-1930

## [19:30] 功能实现: 吟游廊 EIP平台从零搭建完成
- 动作：基于设计稿实现门户首页 + 后台管理全栈项目（ThinkPHP 8 + Vue3 + Element Plus + MariaDB/INUEIP）
- 文件：backend/（Auth/Portal/admin 四模块 + JWT/RBAC 中间件）、frontend/（登录页/门户首页/后台四页）、database 迁移+种子
- 决策：① 后端选 ThinkPHP 8（用户确认 PHP 方向）；② 应用链接 section+status+sort 模型实现"管理员配置后才显示"；③ 考勤统计占位接口返回模拟数据，二期对接；④ 本地环境为 XAMPP（MariaDB 10.4 + PHP 8.2），Composer 装在 tools/composer.phar；⑤ 前端演示密码 zhangsan=User@12345
- 验证：php -l 32 文件 0 失败；API 实测（登录/锁定/401/403/CRUD/XSS过滤/上下架）全过；Playwright 真人验证（登录→门户渲染→停用链接首页即时隐藏→普通用户权限边界）全过；npm run build 通过

## [20:35] 踩坑记录: think-migration 表前缀叠加
- 现象：迁移生成的表为 inue_inue_*
- 根因：think-migration 会叠加 .env 的 DB_PREFIX，迁移文件中再写字面前缀会双重叠加
- 修复：迁移/种子文件使用无前缀表名，重建库后 migrate+seed 正常

## [20:40] 踩坑记录: ThinkPHP8 子路径 POST 被列表路由吞掉
- 现象：POST /api/admin/announcements/:id/publish 返回 save() 的校验错误
- 根因：route_complete_match=false（默认）时列表路由前缀匹配吞掉更长子路径
- 修复：config/route.php 开启 route_complete_match=true 且 url_route_must=true（API 项目强制路由）
