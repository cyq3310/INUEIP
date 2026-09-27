<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\User;
use app\service\AuthService;

class Auth extends BaseController
{
    /** 登录（公开接口，含限频锁定） */
    public function login()
    {
        $username = trim((string) $this->request->post('username', ''));
        $password = (string) $this->request->post('password', '');
        if ($username === '' || $password === '') {
            return resp_fail('请输入账号和密码');
        }

        try {
            $result = (new AuthService())->login($username, $password);
        } catch (\RuntimeException $e) {
            return resp_fail($e->getMessage(), $e->getCode() ?: 400);
        }

        write_oplog('auth.login', "用户 {$username} 登录成功");
        return resp_ok($result, '登录成功');
    }

    /** 登出（JWT 无状态，前端清除 Token 即可，此处记录日志） */
    public function logout()
    {
        write_oplog('auth.logout', "用户 {$this->request->userProfile['username']} 退出登录");
        return resp_ok([], '已退出登录');
    }

    /**
     * 修改当前登录用户的密码：校验原密码后更新。
     * 不绑定权限码——所有登录用户（含普通用户）均可自助修改自己的密码
     */
    public function changePassword()
    {
        $oldPassword = (string) $this->request->post('old_password', '');
        $newPassword = (string) $this->request->post('new_password', '');

        if ($oldPassword === '' || $newPassword === '') {
            return resp_fail('请输入原密码和新密码');
        }
        if (strlen($newPassword) < 8) {
            return resp_fail('新密码长度至少8位');
        }
        if ($newPassword === $oldPassword) {
            return resp_fail('新密码不能与原密码相同');
        }

        $user = User::find($this->request->userId);
        if (!$user) {
            return resp_fail('用户不存在', 404);
        }
        if (!password_verify($oldPassword, (string) $user->password_hash)) {
            return resp_fail('原密码错误', 422);
        }

        $user->password_hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $user->save();

        write_oplog('auth.change-password', "用户 {$user->username}(ID:{$user->id}) 修改密码成功");
        return resp_ok([], '密码修改成功');
    }

    /** 当前用户档案 */
    public function profile()
    {
        return resp_ok($this->request->userProfile);
    }
}
