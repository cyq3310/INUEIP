<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Permission extends Model
{
    protected $name               = 'permission';
    protected $autoWriteTimestamp = 'datetime';
}
