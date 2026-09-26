<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class PortalTheme extends Model
{
    protected $name               = 'portal_theme';
    protected $autoWriteTimestamp = 'datetime';
}
