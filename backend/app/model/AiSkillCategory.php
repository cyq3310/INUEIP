<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class AiSkillCategory extends Model
{
    protected $name = 'ai_skill_category';

    protected $autoWriteTimestamp = false;

    protected $type = [
        'id'     => 'integer',
        'sort'   => 'integer',
        'status' => 'integer',
    ];
}
