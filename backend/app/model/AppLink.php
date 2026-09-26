<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class AppLink extends Model
{
    protected $name               = 'app_link';
    protected $autoWriteTimestamp = 'datetime';
}
