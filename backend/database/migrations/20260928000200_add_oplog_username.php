<?php

use think\facade\Db;
use think\migration\Migrator;

/**
 * 操作日志增加操作人账号：user_id 仅为数字，直接查表可读性差，
 * 冗余一列 username 便于阅读；user_id 保留（用于程序关联与按用户筛选，索引仍在）。
 * 同时对存量数据按 user_id 回填 username，幂等。
 */
class AddOplogUsername extends Migrator
{
    public function up(): void
    {
        $table = $this->oplogTable();
        $user  = (string) config('database.connections.mysql.prefix', '') . 'user';

        $this->table('operation_log')
            ->addColumn('username', 'string', [
                'limit'   => 50,
                'null'    => true,
                'after'   => 'user_id',
                'comment' => '操作人账号（冗余，便于直接阅读；登录类无登录态时为空）',
            ])
            ->update();

        // 存量回填：按 user_id 关联用户表补齐账号
        Db::execute(
            "UPDATE `{$table}` ol
                JOIN `{$user}` u ON u.id = ol.user_id
                 SET ol.username = u.username
              WHERE ol.user_id > 0 AND (ol.username IS NULL OR ol.username = '')"
        );
    }

    public function down(): void
    {
        $this->table('operation_log')->removeColumn('username')->update();
    }

    private function oplogTable(): string
    {
        return (string) config('database.connections.mysql.prefix', '') . 'operation_log';
    }
}
