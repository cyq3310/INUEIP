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
     * 仅保留在 EIP 代码库内开发、且仍由 EIP 管理授权的平台。
     * 知识库权限已移交 InuWiki 自建权限系统，故不再注册 kb 页签；
     * 权限行统一按 PermissionModel::retiredIds() 排除（兼容 platform 未校正的存量库）。
     */
    private const PLATFORMS = [
        'eip' => 'EIP 平台',
    ];

    /** 默认平台：未标记或取值未注册（含历史遗留值）时回落，避免权限在界面丢失 */
    private const DEFAULT_PLATFORM = 'eip';

    /**
     * 按平台分组的权限树：平台 → 菜单 → 接口
     * 返回 [{ platform, name, menus: [菜单(含 children 接口)] }]
     */
    public function index()
    {
        $retiredIds = PermissionModel::retiredIds();
        $rows       = PermissionModel::order('sort')->order('id')->select()->toArray();
        if ($retiredIds) {
            $rows = array_values(array_filter(
                $rows,
                static fn(array $row): bool => !in_array((int) $row['id'], $retiredIds, true)
            ));
        }

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

        // 按平台归组：已移交 WIKI 的平台整组跳过（其子接口随菜单一起不展示），
        // 其余菜单 platform 缺失时回落到默认平台
        $grouped = [];
        foreach ($menus as $menu) {
            $platform = (string) ($menu['platform'] ?? '');
            if ($platform === PermissionModel::RETIRED_PLATFORM) {
                continue;
            }
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
