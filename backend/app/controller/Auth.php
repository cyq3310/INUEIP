<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
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

    /** 当前用户档案 */
    public function profile()
    {
        return resp_ok($this->request->userProfile);
    }
}
