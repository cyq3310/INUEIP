<?php
declare(strict_types=1);

namespace app\service;

use app\model\Permission;
use app\model\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\facade\Cache;

/**
 * 认证服务：登录（含限频锁定）、JWT 签发与校验、用户权限档案
 */
class AuthService
{
    private const MAX_FAILS    = 5;
    private const LOCK_SECONDS = 900; // 连续失败5次锁定15分钟

    public function login(string $username, string $password): array
    {
        $failKey = 'login_fail:' . $username;
        $fails   = (int) Cache::get($failKey, 0);
        if ($fails >= self::MAX_FAILS) {
            throw new \RuntimeException('失败次数过多，账号已锁定15分钟', 423);
        }

        $user = User::where('username', $username)->find();
        if (!$user || !password_verify($password, (string) $user->password_hash)) {
            Cache::set($failKey, $fails + 1, self::LOCK_SECONDS);
            throw new \RuntimeException('账号或密码错误', 422);
        }
        if ((int) $user->status !== 1) {
            throw new \RuntimeException('账号已被禁用，请联系管理员', 423);
        }

        Cache::delete($failKey);
        $user->last_login_at = date('Y-m-d H:i:s');
        $user->save();

        return [
            'token' => $this->makeToken((int) $user->id),
            'user'  => $this->buildProfile($user),
        ];
    }

    /** JWT 密钥与有效期：以 myconfig.jwt 为准，留空时回落 .env */
    private function jwtSecret(): string
    {
        return (string) (config('myconfig.jwt.secret') ?: env('JWT_SECRET', ''));
    }

    private function jwtExpire(): int
    {
        return (int) (config('myconfig.jwt.expire') ?: env('JWT_EXPIRE', 7200));
    }

    public function makeToken(int $userId): string
    {
        $now = time();
        return JWT::encode([
            'uid' => $userId,
            'iat' => $now,
            'exp' => $now + $this->jwtExpire(),
        ], $this->jwtSecret(), 'HS256');
    }

    public function verifyToken(string $token): int
    {
        $payload = JWT::decode($token, new Key($this->jwtSecret(), 'HS256'));
        return (int) ($payload->uid ?? 0);
    }

    /**
     * 用户档案：基本信息 + 角色 + 权限码集合（超管拥有全部权限）
     */
    public function buildProfile(User $user): array
    {
        $roles     = $user->roles()->select();
        $roleCodes = array_values(array_column($roles->toArray(), 'code'));
        $isSuper   = in_array('super_admin', $roleCodes, true);

        if ($isSuper) {
            $permissionCodes = Permission::column('code');
        } else {
            $permissionCodes = [];
            foreach ($roles as $role) {
                if ((int) $role->status !== 1) {
                    continue;
                }
                foreach ($role->permissions as $perm) {
                    $permissionCodes[] = $perm->code;
                }
            }
            $permissionCodes = array_values(array_unique($permissionCodes));
        }

        return [
            'id'           => (int) $user->id,
            'username'     => $user->username,
            'nickname'     => $user->nickname,
            'avatar'       => $user->avatar,
            'roles'        => $roleCodes,
            'isSuperAdmin' => $isSuper,
            'permissions'  => $permissionCodes,
        ];
    }
}
