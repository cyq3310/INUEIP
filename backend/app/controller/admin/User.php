<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Role;
use app\model\User as UserModel;
use think\facade\Db;

class User extends BaseController
{
    /** 用户列表（分页 + 关键字搜索） */
    public function index()
    {
        $page    = max(1, (int) $this->request->get('page', 1));
        $size    = min(100, max(1, (int) $this->request->get('size', 10)));
        $keyword = trim((string) $this->request->get('keyword', ''));

        $query = UserModel::with('roles')->order('id', 'asc');
        if ($keyword !== '') {
            $query->whereLike('username|nickname', '%' . $keyword . '%');
        }
        $paginator = $query->paginate(['list_rows' => $size, 'page' => $page]);

        return resp_ok(['total' => $paginator->total(), 'list' => $paginator->items()]);
    }

    /** 新增用户 */
    public function save()
    {
        $data     = $this->request->post();
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $nickname = trim((string) ($data['nickname'] ?? ''));
        $roleIds  = array_map('intval', (array) ($data['role_ids'] ?? []));

        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            return resp_fail('账号需为3-20位字母、数字或下划线');
        }
        if (mb_strlen($nickname) === 0 || mb_strlen($nickname) > 50) {
            return resp_fail('昵称不能为空且不超过50字');
        }
        if (strlen($password) < 8) {
            return resp_fail('密码长度至少8位');
        }
        if (UserModel::where('username', $username)->find()) {
            return resp_fail('账号已存在');
        }

        $user = Db::transaction(function () use ($username, $password, $nickname, $roleIds) {
            $user = UserModel::create([
                'username'      => $username,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                'nickname'      => $nickname,
                'status'        => 1,
            ]);
            $this->syncUserRoles((int) $user->id, $roleIds);
            return $user;
        });

        write_oplog('user.create', "新增用户 {$username}(ID:{$user->id})");
        return resp_ok(['id' => (int) $user->id], '新增成功');
    }

    /** 编辑用户 */
    public function update(int $id)
    {
        $user = UserModel::find($id);
        if (!$user) {
            return resp_fail('用户不存在', 404);
        }

        $data     = $this->request->put();
        $nickname = isset($data['nickname']) ? trim((string) $data['nickname']) : (string) $user->nickname;
        $status   = isset($data['status']) ? (int) $data['status'] : (int) $user->status;
        $password = (string) ($data['password'] ?? '');
        $roleIds  = isset($data['role_ids']) ? array_map('intval', (array) $data['role_ids']) : null;

        if (mb_strlen($nickname) === 0 || mb_strlen($nickname) > 50) {
            return resp_fail('昵称不能为空且不超过50字');
        }
        if ($password !== '' && strlen($password) < 8) {
            return resp_fail('密码长度至少8位');
        }
        if ($status !== 1 && (int) $user->id === (int) $this->request->userId) {
            return resp_fail('不能禁用自己的账号');
        }
        if ($status !== 1 && (int) $user->id === 1) {
            return resp_fail('内置超级管理员不能禁用');
        }

        Db::transaction(function () use ($user, $nickname, $status, $password, $roleIds) {
            $user->nickname = $nickname;
            $user->status   = $status;
            if ($password !== '') {
                $user->password_hash = password_hash($password, PASSWORD_BCRYPT);
            }
            $user->save();
            if ($roleIds !== null) {
                $this->syncUserRoles((int) $user->id, $roleIds);
            }
        });

        write_oplog('user.update', "编辑用户 {$user->username}(ID:{$user->id})");
        return resp_ok([], '保存成功');
    }

    /** 删除用户（软删除，保护内置超管与当前登录人） */
    public function delete(int $id)
    {
        if ($id === 1) {
            return resp_fail('内置超级管理员不能删除');
        }
        if ($id === (int) $this->request->userId) {
            return resp_fail('不能删除自己的账号');
        }
        $user = UserModel::find($id);
        if (!$user) {
            return resp_fail('用户不存在', 404);
        }

        Db::transaction(function () use ($user, $id) {
            Db::name('user_role')->where('user_id', $id)->delete();
            $user->delete();
        });

        write_oplog('user.delete', "删除用户 {$user->username}(ID:{$id})");
        return resp_ok([], '删除成功');
    }

    /** 重置密码：不传则随机生成并返回一次 */
    public function resetPassword(int $id)
    {
        $user = UserModel::find($id);
        if (!$user) {
            return resp_fail('用户不存在', 404);
        }

        $password = (string) $this->request->post('password', '');
        if ($password === '') {
            $password = $this->generatePassword();
        } elseif (strlen($password) < 8) {
            return resp_fail('密码长度至少8位');
        }

        $user->password_hash = password_hash($password, PASSWORD_BCRYPT);
        $user->save();

        write_oplog('user.reset-password', "重置用户 {$user->username}(ID:{$id}) 的密码");
        return resp_ok(['password' => $password], '密码已重置');
    }

    private function syncUserRoles(int $userId, array $roleIds): void
    {
        Db::name('user_role')->where('user_id', $userId)->delete();
        if (empty($roleIds)) {
            return;
        }
        $validIds = Role::whereIn('id', $roleIds)->column('id');
        $rows     = array_map(fn($rid) => ['user_id' => $userId, 'role_id' => (int) $rid], $validIds);
        if ($rows) {
            Db::name('user_role')->insertAll($rows);
        }
    }

    private function generatePassword(int $length = 10): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $max   = strlen($chars) - 1;
        $pwd   = '';
        for ($i = 0; $i < $length; $i++) {
            $pwd .= $chars[random_int(0, $max)];
        }
        return $pwd . '@' . random_int(10, 99);
    }
}
