# 增量数据库脚本规范

本目录存放**生产可用的增量 SQL 脚本**，与 `backend/database/migrations/` 下的 think-migration 迁移文件一一对应。

之所以要"双份"：生产环境可能没有 composer 依赖、无法执行 `php think migrate`，此时直接用本目录的 `.up.sql` 执行即可。

## 命名规则

```
docs/sql/YYYYMMDDHHmm_{简述}.up.sql
docs/sql/YYYYMMDDHHmm_{简述}.down.sql
```

- 时间戳与对应迁移文件保持一致（`20260920000100_add_announcement_top_fields.php` → `20260920000100_add_announcement_top_fields.up.sql`）。
- `{简述}` 用小写下划线，与迁移文件同名，便于对照。
- `up` 为升级脚本，`down` 为回滚脚本，**必须成对产出**。

## 脚本头部注释模板（每个脚本必须包含）

```sql
-- 版本: v1.0.0
-- 适用环境: production / all
-- 影响表: inue_announcement
-- 幂等: 是（使用 IF NOT EXISTS）
-- 回滚脚本: 同名 .down.sql
-- 对应迁移: backend/database/migrations/20260920000100_add_announcement_top_fields.php
-- 说明: 公告表新增置顶字段与置顶时间
```

## 编写要求

1. **幂等优先**：DDL 尽量用 `IF NOT EXISTS` / `IF EXISTS`，重复执行不报错。
2. **可回滚**：`down.sql` 必须能把结构恢复到执行前；确实无法回滚的（如清除了历史数据），在头部注明"不可回滚"，并给出备份表或备份库方案。
3. **不写死库名**：脚本内不出现 `USE xxx` 与库名前缀，由执行环境指定库；表名使用统一前缀 `inue_`。
4. **数据变更与结构变更分开**：同一需求既有改表又有刷数据时，拆成两个脚本，便于定位问题。
5. **显式字符集**：建表/加字段统一 `utf8mb4`。

## 生产执行顺序

1. **备份**：先对涉及的表做全量备份（或整库备份）。
2. **执行 up**：按时间戳升序执行本次发布涉及的所有 `.up.sql`。
3. **校验**：确认表结构/数据符合预期，应用功能自测通过。
4. **回滚**：出现异常则按时间戳**降序**执行对应 `.down.sql`，再恢复备份。

## 基线说明

`backend/database/migrations/` 中在 `docs/sql/` 建立之前的 4 个迁移（rbac、portal、portal_theme、announcement_top）视为**初始化基线**，不补写历史 SQL；从本规范生效起的每一次变更都必须同时产出迁移文件与 up/down 脚本。

## 清单维护

每次发布时，发布检查清单中需列出本次涉及的脚本文件名与执行顺序，参见 `.codebuddy/rules/统一配置与发布规范.md`。
