<?php

use think\migration\Migrator;

/**
 * 权限归属平台：用于「分配权限」按平台页签分组展示
 * platform 仅为分组属性，不参与授权判定；eip=EIP 平台，skill=AI Skill 平台
 */
class AddPermissionPlatform extends Migrator
{
    public function up(): void
    {
        $this->table('permission')
            ->addColumn('platform', 'string', ['limit' => 20, 'default' => 'eip', 'comment' => '归属平台 eip=EIP平台 skill=AI Skill平台'])
            ->update();
    }

    public function down(): void
    {
        $this->table('permission')
            ->removeColumn('platform')
            ->update();
    }
}
