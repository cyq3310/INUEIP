<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class OperationLog extends Model
{
    protected $name               = 'operation_log';
    protected $autoWriteTimestamp = false;
}
