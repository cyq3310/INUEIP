<?php
declare(strict_types=1);

namespace app\middleware;

/**
 * 接口级权限中间件：按权限码校验（超管放行），需在 AuthCheck 之后执行
 */
class PermissionCheck
{
    public function handle($request, \Closure $next, string $code)
    {
        $profile = $request->userProfile ?? null;
        if (!$profile) {
            return resp_fail('未登录或登录已过期', 401);
        }
        if (!empty($profile['isSuperAdmin']) || in_array($code, $profile['permissions'] ?? [], true)) {
            return $next($request);
        }
        return resp_fail('无权限执行该操作', 403);
    }
}
