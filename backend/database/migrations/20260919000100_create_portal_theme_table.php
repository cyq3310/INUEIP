<?php

use think\migration\Migrator;

/**
 * 门户分区背景图配置：按模块维度记录背景图路径与不透明度
 * 图片文件按模块分目录存放于 public/uploads/portal/{module}/
 */
class CreatePortalThemeTable extends Migrator
{
    public function up(): void
    {
        $innodb = ['engine' => 'InnoDB', 'collation' => 'utf8mb4_general_ci'];

        $this->table('portal_theme', array_merge($innodb, ['id' => false, 'primary_key' => ['id'], 'comment' => '门户分区背景图配置表']))
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '主键'])
            ->addColumn('module', 'string', ['limit' => 50, 'comment' => '模块标识，与图片存放子目录同名'])
            ->addColumn('name', 'string', ['limit' => 100, 'default' => '', 'comment' => '模块展示名'])
            ->addColumn('image_path', 'string', ['limit' => 500, 'null' => true, 'comment' => '背景图访问路径，为空表示未启用'])
            ->addColumn('opacity', 'integer', ['limit' => 3, 'default' => 100, 'comment' => '不透明度 0-100'])
            ->addColumn('updated_by', 'biginteger', ['signed' => false, 'null' => true, 'comment' => '最后修改人'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addIndex(['module'], ['unique' => true, 'name' => 'uk_portal_theme_module'])
            ->create();
    }

    public function down(): void
    {
        $this->table('portal_theme')->drop()->save();
    }
}
