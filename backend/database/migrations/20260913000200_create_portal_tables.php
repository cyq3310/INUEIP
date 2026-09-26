<?php

use think\migration\Migrator;

/**
 * 门户业务四表：应用链接 / 公告 / 考勤统计占位 / 操作日志
 */
class CreatePortalTables extends Migrator
{
    public function up(): void
    {
        $innodb = ['engine' => 'InnoDB', 'collation' => 'utf8mb4_general_ci'];

        // 应用链接表（协作与办公 / 自定义工具，管理员配置后才显示）
        $this->table('app_link', array_merge($innodb, ['id' => false, 'primary_key' => ['id'], 'comment' => '应用链接配置表']))
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '主键'])
            ->addColumn('name', 'string', ['limit' => 100, 'comment' => '应用名称'])
            ->addColumn('icon', 'string', ['limit' => 100, 'comment' => '图标标识(lucide)'])
            ->addColumn('subtitle', 'string', ['limit' => 255, 'null' => true, 'comment' => '副标题描述'])
            ->addColumn('url', 'string', ['limit' => 500, 'comment' => '跳转地址'])
            ->addColumn('section', 'enum', ['values' => ['collab', 'custom'], 'comment' => '分区 collab协作与办公 custom自定义工具'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序（越小越靠前）'])
            ->addColumn('status', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '状态 1启用 0停用'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addIndex(['section', 'status', 'sort'], ['name' => 'idx_app_link_section_status_sort'])
            ->create();

        // 公告表
        $this->table('announcement', array_merge($innodb, ['id' => false, 'primary_key' => ['id'], 'comment' => '公告表']))
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '主键'])
            ->addColumn('title', 'string', ['limit' => 200, 'comment' => '公告标题'])
            ->addColumn('summary', 'string', ['limit' => 500, 'null' => true, 'comment' => '副标题/摘要'])
            ->addColumn('content', 'text', ['limit' => 16777215, 'null' => true, 'comment' => '富文本正文(HTML)'])
            ->addColumn('status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '状态 0草稿 1发布 2下架'])
            ->addColumn('published_at', 'datetime', ['null' => true, 'comment' => '发布时间'])
            ->addColumn('created_by', 'biginteger', ['signed' => false, 'comment' => '创建人用户ID'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '软删除时间'])
            ->addIndex(['status', 'published_at'], ['name' => 'idx_announcement_status_published'])
            ->create();

        // 考勤统计占位表（本期不落真实数据，预留对接真实考勤系统）
        $this->table('attendance_stat', array_merge($innodb, ['id' => false, 'primary_key' => ['id'], 'comment' => '考勤每日统计表']))
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '主键'])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'comment' => '用户ID'])
            ->addColumn('stat_date', 'date', ['comment' => '统计日期'])
            ->addColumn('unclock_count', 'integer', ['default' => 0, 'comment' => '未打卡次数'])
            ->addColumn('late_count', 'integer', ['default' => 0, 'comment' => '迟到次数'])
            ->addColumn('pending_count', 'integer', ['default' => 0, 'comment' => '待审批数'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addIndex(['user_id', 'stat_date'], ['unique' => true, 'name' => 'uk_attendance_user_date'])
            ->create();

        // 操作日志表
        $this->table('operation_log', array_merge($innodb, ['id' => false, 'primary_key' => ['id'], 'comment' => '后台操作日志表']))
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '主键'])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'comment' => '操作人用户ID'])
            ->addColumn('action', 'string', ['limit' => 100, 'comment' => '操作动作'])
            ->addColumn('detail', 'text', ['null' => true, 'comment' => '操作详情'])
            ->addColumn('ip', 'string', ['limit' => 45, 'null' => true, 'comment' => '操作IP'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addIndex(['user_id', 'created_at'], ['name' => 'idx_oplog_user_created'])
            ->create();
    }

    public function down(): void
    {
        $this->table('operation_log')->drop()->save();
        $this->table('attendance_stat')->drop()->save();
        $this->table('announcement')->drop()->save();
        $this->table('app_link')->drop()->save();
    }
}
