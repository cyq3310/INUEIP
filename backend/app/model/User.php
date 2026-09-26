<?php
declare(strict_types=1);

namespace app\model;

use think\Model;
use think\model\concern\SoftDelete;

class User extends Model
{
    use SoftDelete;

    protected $name               = 'user';
    protected $autoWriteTimestamp = 'datetime';
    protected $deleteTime         = 'deleted_at';
    protected $hidden             = ['password_hash'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role', 'role_id', 'user_id');
    }
}
