<?php

use think\facade\Db;
use think\migration\Seeder;

/**
 * 公告置顶与正文图片上传权限：挂到「公告管理」菜单（permission id 30）下，并分配给超级管理员
 * 幂等：按 code 判断权限是否已存在，按已分配关系去重
 */
class AnnouncementTopSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $permissions = [
            ['id' => 36, 'parent_id' => 30, 'name' => '公告置顶', 'code' => 'announcement:top', 'type' => 'api', 'path' => 'POST /api/admin/announcements/:id/top', 'sort' => 6],
            ['id' => 37, 'parent_id' => 30, 'name' => '公告图片上传', 'code' => 'announcement:upload', 'type' => 'api', 'path' => 'POST /api/admin/announcements/upload', 'sort' => 7],
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
