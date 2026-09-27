<?php

use think\facade\Db;
use think\migration\Migrator;

/**
 * 知识库权限归类：现有 kb:* 权限全部散落在顶层（parent_id=0），
 * 导致「分配权限」中知识库的各个接口没有归属到知识库菜单标签下。
 *
 * 处理：建立「知识库」菜单（system:kb，归属 EIP 平台），并将 kb:* 权限统一挂到该菜单下；
 * 原 id=75「知识库查看」虽为 menu 类型，实为查看接口，一并转为 api 作为菜单子项。
 * 幂等：无 kb:* 数据时直接跳过。
 */
class GroupKbPermissions extends Migrator
{
    private const KB_CODES = [
        'kb:article:list',
        'kb:article:create',
        'kb:article:update',
        'kb:article:delete',
        'kb:article:publish',
        'kb:category:manage',
        'kb:space:manage',
        'kb:media:upload',
    ];

    private const KB_MENU_CODE = 'system:kb';

    public function up(): void
    {
        if (!Db::name('permission')->whereIn('code', self::KB_CODES)->find()) {
            return;
        }

        $now  = date('Y-m-d H:i:s');
        $menu = Db::name('permission')->where('code', self::KB_MENU_CODE)->find();
        if ($menu) {
            $menuId = (int) $menu['id'];
        } else {
            $menuId = (int) Db::name('permission')->insertGetId([
                'parent_id'  => 0,
                'name'       => '知识库',
                'code'       => self::KB_MENU_CODE,
                'type'       => 'menu',
                'path'       => '',
                'sort'       => 5,
                'platform'   => 'eip',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Db::name('permission')
            ->whereIn('code', self::KB_CODES)
            ->update([
                'parent_id'  => $menuId,
                'type'       => 'api',
                'platform'   => 'eip',
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        $rows = Db::name('permission')->whereIn('code', self::KB_CODES)->select()->toArray();
        if (!$rows) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        Db::name('permission')
            ->whereIn('code', self::KB_CODES)
            ->update(['parent_id' => 0, 'updated_at' => $now]);

        // 恢复原「知识库查看」为菜单类型（按名称定位，避免依赖固定 id）
        Db::name('permission')
            ->where('code', 'kb:article:list')
            ->update(['type' => 'menu', 'updated_at' => $now]);

        Db::name('permission')->where('code', self::KB_MENU_CODE)->delete();
    }
}
