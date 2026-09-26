<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class AiSkill extends Model
{
    protected $name = 'ai_skill';

    // 时间字段由控制器显式写入，避免依赖框架的 create_time/update_time 默认字段名
    protected $autoWriteTimestamp = false;

    /** params 存 JSON，读写自动序列化 */
    protected $json      = ['params'];
    protected $jsonAssoc = true;

    /** 输出给前端时统一转为数值，避免 bigint/tinyint 被 PDO 转成字符串 */
    protected $type = [
        'id'                   => 'integer',
        'function_category_id' => 'integer',
        'type_category_id'     => 'integer',
        'owner_id'             => 'integer',
        'status'               => 'integer',
        'like_count'           => 'integer',
        // 必须在 type 中显式声明为 json：本版本 think-orm 的 getFields() 只合并 $type 与
        // schema(text 会被识别为 string)，并不会把 $json 并入字段类型。若不声明，set(params, 数组)
        // 会走 (string)$array 触发「Array to string conversion」，且 params 不会被正确序列化。
        'params'               => 'json',
    ];
}
