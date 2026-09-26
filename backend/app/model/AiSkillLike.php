<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * Skill 点赞记录：(skill_id, user_id) 唯一，保证每人对同一 Skill 仅计一次
 */
class AiSkillLike extends Model
{
    protected $name = 'ai_skill_like';

    protected $autoWriteTimestamp = false;
}
