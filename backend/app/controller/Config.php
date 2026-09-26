<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use think\facade\Log;

/**
 * 平台公开配置下发
 *
 * 前端启动时调用 GET /api/config 拉取配置，实现前后端共用同一份 myconfig。
 * 只按 myconfig.frontend_public.keys 白名单下发；database / jwt 等私密分组即使被误加白名单也会被剔除。
 */
class Config extends BaseController
{
    /** 绝对禁止下发的分组（私密项） */
    private const FORBIDDEN_GROUPS = ['database', 'jwt'];

    /** 白名单缺失时使用的安全缺省值（均为可公开项） */
    private const DEFAULTS = [
        'deploy.env'               => 'production',
        'site.name'                => '',
        'site.version'             => '',
        'site.copyright'           => '',
        'site.icp'                 => '',
        'deploy.api.base_url'      => '/api',
        'deploy.api.timeout'       => 15,
        'deploy.web.base_url'      => '',
        'deploy.resource.mode'     => 'local',
        'deploy.resource.base_url' => '',
        'skill.source'             => 'local',
        'skill.use_mock'           => false,
        'skill.local_path'         => '/uploads/skills',
        'skill.remote_base_url'    => '',
        'portal.page_size'         => 10,
    ];

    /**
     * GET /api/config：免登录公开配置
     *
     * 返回结构：{ env, site:{name,version,copyright,icp}, api:{base_url,timeout},
     *             web:{base_url}, resource:{mode,base_url},
     *             skill:{source,use_mock,remote_base_url}, portal:{page_size} }
     */
    public function publicConfig()
    {
        $white = $this->safeWhitelist();

        // 未进白名单的键返回 null，由 $default 兜底，保证前端始终拿到完整结构
        $pick = fn (string $key) => $this->pick($key, $white) ?? (self::DEFAULTS[$key] ?? null);

        return resp_ok([
            'env'      => (string) $pick('deploy.env'),
            'site'     => [
                'name'      => (string) $pick('site.name'),
                'version'   => (string) $pick('site.version'),
                'copyright' => (string) $pick('site.copyright'),
                'icp'       => (string) $pick('site.icp'),
            ],
            'api'      => [
                'base_url' => (string) $pick('deploy.api.base_url'),
                'timeout'  => (int) $pick('deploy.api.timeout'),
            ],
            'web'      => [
                'base_url' => (string) $pick('deploy.web.base_url'),
            ],
            'resource' => [
                'mode'     => (string) $pick('deploy.resource.mode'),
                'base_url' => (string) $pick('deploy.resource.base_url'),
            ],
            'skill'    => [
                'source'          => (string) $pick('skill.source'),
                'use_mock'        => (bool) $pick('skill.use_mock'),
                'local_path'      => (string) $pick('skill.local_path'),
                'remote_base_url' => (string) $pick('skill.remote_base_url'),
            ],
            'portal'   => [
                'page_size' => (int) $pick('portal.page_size'),
            ],
        ]);
    }

    /**
     * 取白名单，并剔除私密分组
     */
    private function safeWhitelist(): array
    {
        $keys = config('myconfig.frontend_public.keys', []);
        if (!is_array($keys)) {
            return [];
        }

        $safe = [];
        foreach ($keys as $key) {
            $key   = (string) $key;
            $group = strstr($key, '.', true);
            if (in_array($group, self::FORBIDDEN_GROUPS, true)) {
                Log::warning('myconfig 白名单含私密分组，已忽略：' . $key);
                continue;
            }
            $safe[] = $key;
        }
        return $safe;
    }

    /**
     * 按白名单取值；不在白名单或属于私密分组时返回 null
     */
    private function pick(string $key, array $white)
    {
        $group = strstr($key, '.', true);
        if (in_array($group, self::FORBIDDEN_GROUPS, true)) {
            return null;
        }
        if (!in_array($key, $white, true)) {
            return null;
        }
        return config('myconfig.' . $key);
    }
}
