<?php
declare (strict_types = 1);

namespace app;

use think\Service;

/**
 * 应用服务类
 */
class AppService extends Service
{
    public function register()
    {
        // 服务注册
    }

    public function boot()
    {
        // myconfig 为唯一配置源：用 myconfig.database 覆盖框架数据库配置（空值回落 .env / 默认值）
        $this->syncDatabaseConfig();
    }

    /**
     * 把 myconfig.database 同步到 database.connections.mysql
     *
     * config/database.php 走 env() 读取，而 env 值不便入库；
     * 这里以 myconfig 为准覆盖，保证"配置只写一处"。myconfig 中留空的项保持 env 值不变。
     */
    private function syncDatabaseConfig(): void
    {
        $my = config('myconfig.database', []);
        if (!is_array($my) || $my === []) {
            return;
        }

        $map = [
            'type'     => 'type',
            'hostname' => 'hostname',
            'hostport' => 'hostport',
            'database' => 'database',
            'username' => 'username',
            'password' => 'password',
            'prefix'   => 'prefix',
            'charset'  => 'charset',
        ];

        $conn    = config('database.connections.mysql', []);
        $changed = false;
        foreach ($map as $myKey => $cfgKey) {
            $value = $my[$myKey] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $conn[$cfgKey] = $value;
            $changed       = true;
        }

        if ($changed) {
            $this->app->config->set(['connections' => ['mysql' => $conn]], 'database');
        }
    }
}
