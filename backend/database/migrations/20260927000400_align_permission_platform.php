<?php

use think\facade\Db;
use think\migration\Migrator;

/**
 * 平台归属校正（按「功能是否开发在 EIP 代码库内」判定）：
 * - AI Skill 与 Skill 分类开发在 EIP 代码库内 → 归入 EIP 平台（eip）
 * - 知识库 InuWiki 为独立产品 → 独立页签（kb）
 *
 * 幂等：按 code 批量更新，重复执行结果一致。
 * 不修改已执行的迁移 003，由本条统一纠正，保证存量库与全新安装结果一致。
 */
class AlignPermissionPlatform extends Migrator
{
    /** 开发在 EIP 代码库内的模块权限 */
    private const EIP_CODES = [
        'ai-skill:manage-all',
        'ai-skill:list',
        'ai-skill:update',
        'ai-skill:delete',
        'ai-skill:publish',
        'ai-skill:create',
        'ai-skill:parse-zip',
        'ai-skill:like',
        'ai-skill:stats',
        'ai-skill:categories',
        'ai-skill:category',
        'skill-category:list',
        'skill-category:create',
        'skill-category:update',
        'skill-category:delete',
    ];

    /** 知识库（InuWiki）独立平台的菜单与接口权限 */
    private const KB_CODES = [
        'system:kb',
        'kb:article:list',
        'kb:article:create',
        'kb:article:update',
        'kb:article:delete',
        'kb:article:publish',
        'kb:category:manage',
        'kb:space:manage',
        'kb:media:upload',
    ];

    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        Db::name('permission')
            ->whereIn('code', self::EIP_CODES)
            ->update(['platform' => 'eip', 'updated_at' => $now]);

        Db::name('permission')
            ->whereIn('code', self::KB_CODES)
            ->update(['platform' => 'kb', 'updated_at' => $now]);
    }

    public function down(): void
    {
        $now = date('Y-m-d H:i:s');

        Db::name('permission')
            ->whereIn('code', self::EIP_CODES)
            ->update(['platform' => 'skill', 'updated_at' => $now]);

        Db::name('permission')
            ->whereIn('code', self::KB_CODES)
            ->update(['platform' => 'eip', 'updated_at' => $now]);
    }
}
