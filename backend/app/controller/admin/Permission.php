<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Permission as PermissionModel;

class Permission extends BaseController
{
    /** 权限树（菜单为父、接口权限为子） */
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

        return resp_ok($buildTree(0));
    }
}
