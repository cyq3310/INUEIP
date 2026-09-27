<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Permission as PermissionModel;

class Permission extends BaseController
{
    /**
     * 平台分组定义：与 permission.platform 取值对应，用于「分配权限」按平台页签展示
     * 顺序即页签顺序。归属判定见 .codebuddy/rules/权限平台分组规范.md：
     * 功能开发在 EIP 代码库内 → eip；独立产品（如知识库 InuWiki）→ 独立键值与页签
     */
    private const PLATFORMS = [
        'eip' => 'EIP 平台',
        'kb'  => '知识库平台',
    ];

    /** 默认平台：未标记或取值未注册（含历史遗留值）时回落，避免权限在界面丢失 */
    private const DEFAULT_PLATFORM = 'eip';

    /**
     * 按平台分组的权限树：平台 → 菜单 → 接口
     * 返回 [{ platform, name, menus: [菜单(含 children 接口)] }]
     */
    public function index()
    {
        $rows = PermissionModel::order('sort')->order('id')->select()->toArray();

        $childrenMap = [];
        foreach ($rows as $row) {
            $childrenMap[(int) $row['parent_id']][] = $row;
        }

        $buildTree = function (int $parentId) use (&$buildTree, $childrenMap): array {
            $tree = [];
            foreach ($childrenMap[$parentId] ?? [] as $node) {
                $node['children'] = $buildTree((int) $node['id']);
                $tree[]           = $node;
            }
            return $tree;
        };

        $menus = $buildTree(0);

        // 按平台归组：菜单的 platform 缺失时回落到默认平台
        $grouped = [];
        foreach ($menus as $menu) {
            $platform = (string) ($menu['platform'] ?? '');
            if ($platform === '' || !isset(self::PLATFORMS[$platform])) {
                $platform = self::DEFAULT_PLATFORM;
            }
            $grouped[$platform][] = $menu;
        }

        $result = [];
        foreach (self::PLATFORMS as $platform => $name) {
            if (empty($grouped[$platform])) {
                continue;
            }
            $result[] = [
                'platform' => $platform,
                'name'     => $name,
                'menus'    => $grouped[$platform],
            ];
        }

        return resp_ok($result);
    }
}
