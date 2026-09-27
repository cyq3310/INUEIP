<?php

use think\facade\Db;
use think\migration\Migrator;

/**
 * AI Skill 权限的平台归属：将 AI Skill 相关权限标记为 skill 平台，
 * 使「分配权限」能按平台页签分组展示。
 *
 * 放在迁移而非 Seeder 中：Seeder 内 Phinx 插入与原生更新同表会互相等待行锁。
 * 幂等：重复执行结果一致。
 */
class SetAiSkillPlatform extends Migrator
{
    private const SKILL_CODES = [
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

    public function up(): void
    {
        Db::name('permission')
            ->whereIn('code', self::SKILL_CODES)
            ->update(['platform' => 'skill', 'updated_at' => date('Y-m-d H:i:s')]);
    }

    public function down(): void
    {
        Db::name('permission')
            ->whereIn('code', self::SKILL_CODES)
            ->update(['platform' => 'eip', 'updated_at' => date('Y-m-d H:i:s')]);
    }
}
