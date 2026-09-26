<?php
// 应用公共文件

use think\Response;

/**
 * 统一成功响应 {code:0, msg, data}
 */
function resp_ok($data = [], string $msg = 'ok'): Response
{
    return json(['code' => 0, 'msg' => $msg, 'data' => $data]);
}

/**
 * 统一失败响应 {code:非0, msg, data}
 */
function resp_fail(string $msg = 'error', int $code = 400, $data = null, int $httpStatus = 200): Response
{
    return json(['code' => $code, 'msg' => $msg, 'data' => $data], $httpStatus);
}

/**
 * 上传资源访问前缀（取自 myconfig，缺省回落到内置默认值）
 *
 * @param string $group myconfig 中的资源分组：announcement|portal_theme
 */
function upload_prefix(string $group): string
{
    $defaults = ['announcement' => '/uploads/announcement', 'portal_theme' => '/uploads/portal'];
    return (string) (config('myconfig.' . $group . '.upload_prefix') ?: ($defaults[$group] ?? '/uploads/' . $group));
}

/**
 * 上传资源物理目录：upload.root + 访问前缀去掉 url_prefix 后的相对目录
 *
 * 例：root=/.../public/uploads、url_prefix=/uploads、announcement.upload_prefix=/uploads/announcement
 *     → upload_dir('announcement', 'tmp') = /.../public/uploads/announcement/tmp
 */
function upload_dir(string $group, string $subDir = ''): string
{
    $root   = rtrim((string) (config('myconfig.upload.root') ?: root_path() . 'public/uploads'), '/\\');
    $prefix = trim(upload_prefix($group), '/');
    $base   = trim((string) (config('myconfig.upload.url_prefix') ?: '/uploads'), '/');

    if ($base !== '' && str_starts_with($prefix, $base . '/')) {
        $prefix = substr($prefix, strlen($base) + 1);
    }

    $dir = $root . '/' . $prefix;
    return $subDir === '' ? $dir : $dir . '/' . $subDir;
}

/**
 * 允许上传的扩展名（逗号串），取自 myconfig.upload.allowed_ext
 */
function upload_allowed_ext(): string
{
    $ext = config('myconfig.upload.allowed_ext', []);
    if (is_array($ext) && $ext !== []) {
        return implode(',', $ext);
    }
    return (string) $ext ?: 'jpg,jpeg,png,webp,gif';
}

/**
 * 单文件上传上限（字节），取自 myconfig.upload.max_size
 */
function upload_max_size(): int
{
    return (int) (config('myconfig.upload.max_size') ?: 5242880);
}

/**
 * 上传资源物理根目录（绝对路径）：来自 myconfig.upload.root，缺省回落到 public/uploads
 */
function upload_root(): string
{
    return rtrim((string) (config('myconfig.upload.root') ?: root_path() . 'public/uploads'), '/\\');
}

/**
 * 统一回收站根目录（绝对路径）：来自 myconfig.trash.root，缺省回落到 upload.root/trash
 */
function trash_root(): string
{
    return rtrim((string) (config('myconfig.trash.root') ?: upload_root() . '/trash'), '/\\');
}

/**
 * 回收站内某业务分组目录：trash_root/{group}
 * group 取值：skill | announcement | portal
 */
function trash_group_dir(string $group): string
{
    return trash_root() . '/' . $group;
}

/**
 * 某资源分组的暂存目录（绝对路径）：upload.root/{group前缀}/_tmp
 * 目录名取自 myconfig.trash.temp_dir
 */
function temp_dir(string $group): string
{
    $name = (string) (config('myconfig.trash.temp_dir') ?: '_tmp');
    return upload_dir($group, $name);
}

/**
 * 把位于 upload.root 下的相对路径（文件或目录）整体移入统一回收站。
 * 回收站结构：trash/{group}/{label}_{时间戳}_{随机}/（目录整体移入；单文件则存入该文件夹并保留原名）。
 * 失败仅记 warning 日志并返回 false，不抛异常——删除接口不应因磁盘异常返回 500。
 *
 * @param string $group         业务分组（决定回收站子目录），如 skill/announcement/portal
 * @param string $sourceRelative 相对于 upload.root 的源路径，如 skills/101、announcement/3
 * @param string|null $label     回收目录的标识前缀，缺省取源 basename，如 skill id
 */
function move_to_trash(string $group, string $sourceRelative, ?string $label = null): bool
{
    try {
        $realRoot = realpath(upload_root());
        if ($realRoot === false) {
            \think\facade\Log::warning('move_to_trash 上传根目录不存在');
            return false;
        }
        $src     = $realRoot . DIRECTORY_SEPARATOR . ltrim($sourceRelative, '/\\');
        $realSrc = (is_file($src) || is_dir($src)) ? realpath($src) : false;
        if ($realSrc === false || str_starts_with($realSrc, $realRoot . DIRECTORY_SEPARATOR) === false) {
            \think\facade\Log::warning('move_to_trash 跳过越界或不存在的路径: ' . $sourceRelative);
            return false;
        }

        $groupDir = trash_group_dir($group);
        if (!is_dir($groupDir) && !mkdir($groupDir, 0755, true) && !is_dir($groupDir)) {
            \think\facade\Log::warning('move_to_trash 回收站分组目录创建失败: ' . $groupDir);
            return false;
        }

        $stamp = date('YmdHis');
        $rand  = bin2hex(random_bytes(3));
        $base  = $label ?? basename($realSrc);
        $safe  = preg_replace('/[^\w\-]/', '_', $base);

        if (is_dir($realSrc)) {
            $target = $groupDir . DIRECTORY_SEPARATOR . $safe . '_' . $stamp . '_' . $rand;
            while (is_dir($target) || is_file($target)) {
                $target .= '_' . bin2hex(random_bytes(2));
            }
            return rename($realSrc, $target);
        }

        $targetDir = $groupDir . DIRECTORY_SEPARATOR . $safe . '_' . $stamp . '_' . $rand;
        if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            \think\facade\Log::warning('move_to_trash 回收目标目录创建失败: ' . $targetDir);
            return false;
        }
        return rename($realSrc, $targetDir . DIRECTORY_SEPARATOR . basename($realSrc));
    } catch (\Throwable $e) {
        \think\facade\Log::warning('move_to_trash 异常: ' . $e->getMessage());
        return false;
    }
}

/**
 * 彻底删除目录（递归），用于 GC 清理回收站超期条目；失败不抛异常，仅记日志
 */
function remove_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $full = $dir . DIRECTORY_SEPARATOR . $name;
        if (is_dir($full)) {
            remove_dir($full);
        } else {
            @unlink($full);
        }
    }
    @rmdir($dir);
}

/**
 * 清理统一回收站中超过保留期的分组目录（按目录 mtime 判断）。
 *
 * @param int  $retentionDays 保留天数
 * @param bool $dryRun        true 时只统计不删除
 * @return array {scanned, removed, would_remove}
 */
function gc_trash(int $retentionDays, bool $dryRun = false): array
{
    $root    = trash_root();
    $scanned = 0;
    $would   = 0;

    if (is_dir($root)) {
        $threshold = time() - $retentionDays * 86400;
        foreach (scandir($root) ?: [] as $group) {
            if ($group === '.' || $group === '..') {
                continue;
            }
            $gdir = $root . DIRECTORY_SEPARATOR . $group;
            if (!is_dir($gdir)) {
                continue;
            }
            foreach (scandir($gdir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $full = $gdir . DIRECTORY_SEPARATOR . $entry;
                if (!is_dir($full)) {
                    continue;
                }
                $scanned++;
                if (filemtime($full) < $threshold) {
                    $would++;
                    if (!$dryRun) {
                        remove_dir($full);
                    }
                }
            }
        }
    }

    return [
        'scanned'     => $scanned,
        'removed'     => $dryRun ? 0 : $would,
        'would_remove' => $would,
    ];
}

/**
 * 写入后台操作日志（失败不阻断主流程）
 */
function write_oplog(string $action, string $detail = ''): void
{
    try {
        $request = request();
        \app\model\OperationLog::create([
            'user_id'    => (int) ($request->userId ?? 0),
            'action'     => $action,
            'detail'     => mb_substr($detail, 0, 2000),
            'ip'         => $request->ip(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    } catch (\Throwable $e) {
        \think\facade\Log::error('操作日志写入失败: ' . $e->getMessage());
    }
}
