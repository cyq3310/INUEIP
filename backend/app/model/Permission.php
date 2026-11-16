<?php
declare(strict_types=1);

namespace app\model;

use think\facade\Db;
use think\Model;

class Permission extends Model
{
    protected $name               = 'permission';
    protected $autoWriteTimestamp = 'datetime';

    /** 已移交 InuWiki 自建权限系统的平台标记 */
    public const RETIRED_PLATFORM = 'kb';
    /** 知识库权限码前缀与菜单顶点：兼容未执行平台校正迁移的存量库 */
    public const RETIRED_CODE_PREFIX = 'kb:';
    public const RETIRED_MENU_CODE   = 'system:kb';

    /**
     * 已移交 WIKI 的权限 id 集合。
     * 以 code 判定为主：WIKI 的 seeder 直接把 `kb:*` 以 parent_id=0 写入 EIP 权限表且不指定 platform，
     * 这类存量数据的 platform 是默认 eip，只按 platform=kb 过滤会漏；同时兼容已标记 platform=kb 的库。
     */
    public static function retiredIds(): array
    {
        return array_map('intval', Db::name('permission')
            ->whereRaw(
                '(code LIKE ? OR code = ? OR platform = ?)',
                [self::RETIRED_CODE_PREFIX . '%', self::RETIRED_MENU_CODE, self::RETIRED_PLATFORM]
            )
            ->column('id'));
    }
}
