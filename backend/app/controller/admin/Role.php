<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Permission;
use app\model\Role as RoleModel;
use think\facade\Db;

class Role extends BaseController
{
    /** 角色列表（含已分配权限ID集合） */
    public function index()
    {
        $page      = max(1, (int) $this->request->get('page', 1));
        $size      = min(100, max(1, (int) $this->request->get('size', 20)));
        $paginator = RoleModel::with('permissions')->order('id', 'asc')
            ->paginate(['list_rows' => $size, 'page' => $page]);

        $list = [];
        foreach ($paginator->items() as $role) {
            $list[] = [
                'id'             => (int) $role->id,
                'name'           => $role->name,
                'code'           => $role->code,
                'description'    => $role->description,
                'status'         => (int) $role->status,
                'created_at'     => $role->created_at,
                'permission_ids' => array_map('intval', array_column($role->permissions->toArray(), 'id')),
            ];
        }

        return resp_ok(['total' => $paginator->total(), 'list' => $list]);
    }

    /** 新增角色 */
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

        $role = RoleModel::create([
            'name'        => $name,
            'code'        => $code,
            'description' => trim((string) ($data['description'] ?? '')),
            'status'      => 1,
        ]);

        write_oplog('role.create', "新增角色 {$name}(ID:{$role->id})");
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
        Db::transaction(function () use ($id, $permissionIds) {
            Db::name('role_permission')->where('role_id', $id)->delete();
            if ($permissionIds) {
                $validIds = Permission::whereIn('id', $permissionIds)->column('id');
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
