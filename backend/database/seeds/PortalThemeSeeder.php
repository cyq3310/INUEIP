<?php

use think\facade\Db;
use think\migration\Seeder;

/**
 * 门户背景配置权限：菜单权限 + 接口权限，并分配给超级管理员角色
 * 幂等：按 code 判断权限是否已存在，按已分配关系去重
 */
class PortalThemeSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $permissions = [
            ['id' => 40, 'parent_id' => 0, 'name' => '门户背景', 'code' => 'system:portal-theme', 'type' => 'menu', 'path' => '/admin/portal-themes', 'sort' => 5],
            ['id' => 41, 'parent_id' => 40, 'name' => '背景配置查询', 'code' => 'portal-theme:view', 'type' => 'api', 'path' => 'GET /api/admin/portal-themes', 'sort' => 1],
            ['id' => 42, 'parent_id' => 40, 'name' => '背景配置保存', 'code' => 'portal-theme:update', 'type' => 'api', 'path' => 'POST /api/admin/portal-themes', 'sort' => 2],
            ['id' => 43, 'parent_id' => 40, 'name' => '背景图上传', 'code' => 'portal-theme:upload', 'type' => 'api', 'path' => 'POST /api/admin/portal-themes/upload', 'sort' => 3],
        ];

        $newPermissions = [];
        foreach ($permissions as $permission) {
            if (Db::name('permission')->where('code', $permission['code'])->find()) {
                continue;
            }
            $newPermissions[] = $permission + ['created_at' => $now, 'updated_at' => $now];
        }
        if ($newPermissions) {
            $this->table('permission')->insert($newPermissions)->saveData();
        }

        // 超级管理员角色补齐新增权限
        $superAdminRoleId = 1;
        $permissionIds    = Db::name('permission')->whereIn('code', array_column($permissions, 'code'))->column('id');
        $assignedIds      = array_map('intval', Db::name('role_permission')->where('role_id', $superAdminRoleId)->column('permission_id'));

        $newRolePermissions = [];
        foreach ($permissionIds as $permissionId) {
            if (in_array((int) $permissionId, $assignedIds, true)) {
                continue;
            }
            $newRolePermissions[] = ['role_id' => $superAdminRoleId, 'permission_id' => (int) $permissionId];
        }
        if ($newRolePermissions) {
            $this->table('role_permission')->insert($newRolePermissions)->saveData();
        }
    }
}
