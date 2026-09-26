<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class AttendanceStat extends Model
{
    protected $name               = 'attendance_stat';
    protected $autoWriteTimestamp = 'datetime';
}
