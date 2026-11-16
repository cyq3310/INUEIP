<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Permission;
use app\model\Role as RoleModel;
use think\facade\Db;

class Role extends BaseController
{
    /** 已移交 InuWiki 的权限 id：EIP 侧不再展示、分配、复制（判定统一在模型） */
    private function retiredPermissionIds(): array
    {
        return Permission::retiredIds();
    }

    /** 角色列表（含已分配权限ID集合） */
    public function index()
    {
        $page      = max(1, (int) $this->request->get('page', 1));
        $size      = min(100, max(1, (int) $this->request->get('size', 20)));
        $paginator = RoleModel::with('permissions')->order('id', 'asc')
            ->paginate(['list_rows' => $size, 'page' => $page]);

        $retiredIds = $this->retiredPermissionIds();
        $list       = [];
        foreach ($paginator->items() as $role) {
            $ids    = array_map('intval', array_column($role->permissions->toArray(), 'id'));
            $list[] = [
                'id'             => (int) $role->id,
                'name'           => $role->name,
                'code'           => $role->code,
                'description'    => $role->description,
                'status'         => (int) $role->status,
                'created_at'     => $role->created_at,
                'permission_ids' => array_values(array_diff($ids, $retiredIds)),
            ];
        }

        return resp_ok(['total' => $paginator->total(), 'list' => $list]);
    }

    /** 新增角色（可选复制已有角色的已配置权限） */
    public function save()
    {
        $data = $this->request->post();
        $name = trim((string) ($data['name'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));

        if ($name === '' || mb_strlen($name) > 50) {
            return resp_fail('角色名称不能为空且不超过50字');
        }
        if (!preg_match('/^[a-z0-9_:]{2,50}$/', $code)) {
            return resp_fail('角色编码需为2-50位小写字母、数字、下划线或冒号');
        }
        if (RoleModel::where('code', $code)->find()) {
            return resp_fail('角色编码已存在');
        }

        $copyFrom = (int) ($data['copy_from_role_id'] ?? 0);
        $copyRole = null;
        if ($copyFrom > 0) {
            $copyRole = RoleModel::find($copyFrom);
            if (!$copyRole) {
                return resp_fail('复制来源角色不存在', 404);
            }
        }

        $retiredIds = $copyFrom > 0 ? $this->retiredPermissionIds() : [];
        $role       = null;
        Db::transaction(function () use ($name, $code, $data, $copyFrom, $retiredIds, &$role) {
            $role = RoleModel::create([
                'name'        => $name,
                'code'        => $code,
                'description' => trim((string) ($data['description'] ?? '')),
                'status'      => 1,
            ]);

            if ($copyFrom > 0) {
                $sourceIds = Db::name('role_permission')->where('role_id', $copyFrom)->column('permission_id');
                $validIds  = $sourceIds
                    ? array_values(array_diff(Permission::whereIn('id', $sourceIds)->column('id'), $retiredIds))
                    : [];
                if ($validIds) {
                    $rows = array_map(
                        fn($pid) => ['role_id' => (int) $role->id, 'permission_id' => (int) $pid],
                        $validIds
                    );
                    Db::name('role_permission')->insertAll($rows);
                }
            }
        });

        $detail = "新增角色 {$name}(ID:{$role->id})";
        if ($copyRole) {
            $detail .= "，复制自角色 {$copyRole->name}(ID:{$copyFrom})";
        }
        write_oplog('role.create', $detail);
        return resp_ok(['id' => (int) $role->id], '新增成功');
    }

    /** 编辑角色（内置超管编码与状态受保护） */
    public function update(int $id)
    {
        $role = RoleModel::find($id);
        if (!$role) {
            return resp_fail('角色不存在', 404);
        }

        $data   = $this->request->put();
        $name   = isset($data['name']) ? trim((string) $data['name']) : (string) $role->name;
        $status = isset($data['status']) ? (int) $data['status'] : (int) $role->status;

        if ($name === '' || mb_strlen($name) > 50) {
            return resp_fail('角色名称不能为空且不超过50字');
        }
        if ($status !== 1 && (int) $role->id === 1) {
            return resp_fail('内置超级管理员角色不能禁用');
        }

        $role->name        = $name;
        $role->description = isset($data['description']) ? trim((string) $data['description']) : $role->description;
        $role->status      = $status;
        $role->save();

        write_oplog('role.update', "编辑角色 {$role->name}(ID:{$role->id})");
        return resp_ok([], '保存成功');
    }

    /** 删除角色（内置超管不可删；存在绑定用户时拒绝） */
    public function delete(int $id)
    {
        if ($id === 1) {
            return resp_fail('内置超级管理员角色不能删除');
        }
        $role = RoleModel::find($id);
        if (!$role) {
            return resp_fail('角色不存在', 404);
        }
        if (Db::name('user_role')->where('role_id', $id)->count() > 0) {
            return resp_fail('该角色下仍有用户，请先移除用户后再删除');
        }

        Db::transaction(function () use ($role, $id) {
            Db::name('role_permission')->where('role_id', $id)->delete();
            $role->delete();
        });

        write_oplog('role.delete', "删除角色 {$role->name}(ID:{$id})");
        return resp_ok([], '删除成功');
    }

    /** 分配权限（整组覆盖） */
    public function assignPermissions(int $id)
    {
        $role = RoleModel::find($id);
        if (!$role) {
            return resp_fail('角色不存在', 404);
        }

        $permissionIds = array_map('intval', (array) $this->request->post('permission_ids', []));
        $retiredIds    = $this->retiredPermissionIds();
        Db::transaction(function () use ($id, $permissionIds, $retiredIds) {
            Db::name('role_permission')->where('role_id', $id)->delete();
            if ($permissionIds) {
                $validIds = array_values(array_diff(
                    Permission::whereIn('id', $permissionIds)->column('id'),
                    $retiredIds
                ));
                $rows     = array_map(fn($pid) => ['role_id' => $id, 'permission_id' => (int) $pid], $validIds);
                if ($rows) {
                    Db::name('role_permission')->insertAll($rows);
                }
            }
        });

        write_oplog('role.assign-permission', "为角色 {$role->name}(ID:{$id}) 分配权限：" . implode(',', $permissionIds));
        return resp_ok([], '权限已更新');
    }
}
