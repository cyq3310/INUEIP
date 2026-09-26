<?php
declare(strict_types=1);

namespace app\middleware;

use app\model\User;
use app\service\AuthService;

/**
 * JWT 认证中间件：校验 Bearer Token 并注入用户档案到请求
 */
class AuthCheck
{
    public function handle($request, \Closure $next)
    {
        $header = (string) $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return resp_fail('未登录或登录已过期', 401);
        }

        try {
            $userId = (new AuthService())->verifyToken($matches[1]);
        } catch (\Throwable $e) {
            return resp_fail('登录状态无效，请重新登录', 401);
        }

        $user = User::find($userId);
        if (!$user || (int) $user->status !== 1) {
            return resp_fail('账号不存在或已被禁用', 401);
        }

        $request->userId      = $userId;
        $request->userProfile = (new AuthService())->buildProfile($user);

        return $next($request);
    }
}
