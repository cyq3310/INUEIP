<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Role extends Model
{
    protected $name               = 'role';
    protected $autoWriteTimestamp = 'datetime';

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission', 'permission_id', 'role_id');
    }
}
