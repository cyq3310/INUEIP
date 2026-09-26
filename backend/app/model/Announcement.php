<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

class Announcement extends Model
{
    use SoftDelete;

    protected $name               = 'announcement';
    protected $autoWriteTimestamp = 'datetime';
    protected $deleteTime         = 'deleted_at';
}
