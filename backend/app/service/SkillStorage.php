<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Log;

/**
 * Skill 压缩包文件生命周期管理（唯一文件操作出口）
 *
 * 三段式：
 *  1. 解析阶段（入库前无 ID）→ 落暂存区 skills/_tmp/{ts}_{hex}.zip
 *  2. 入库拿到 ID → 归位到 skills/{id}/package.zip（相对 skills 主目录的路径）
 *  3. 删除（事务外）→ 把整个 {id} 目录移入统一回收站 trash/skill/{id}_{ts}_{rand}/
 *
 * 约定：
 *  - 所有路径相对「skills 主目录」（即 upload_dir('skill') 之下），禁止硬编码绝对路径
 *  - 删除/归位均为单次 rename()，原子、O(1)；文件操作失败只记 warning，不抛异常
 */
class SkillStorage
{
    /** 包内固定文件名 */
    private const PACKAGE_FILE = 'package.zip';

    /** 暂存区（skills 主目录下，目录名取自 myconfig.trash.temp_dir） */
    public function tempDir(): string
    {
        return temp_dir('skill');
    }

    /** 暂存区文件名（不含路径）构造的临时包相对路径，如 _tmp/20260926_ab12cd.zip */
    public function tempRelative(string $filename): string
    {
        $name = (string) (config('myconfig.trash.temp_dir') ?: '_tmp');
        return $name . '/' . $filename;
    }

    /**
     * 把已落盘的上传文件移入暂存区，返回相对 skills 主目录的路径（如 _tmp/xxxx.zip）
     */
    public function stash(string $savedFile, string $filename): string
    {
        $dir = $this->tempDir();
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException('暂存目录创建失败');
        }
        $target = $dir . DIRECTORY_SEPARATOR . $filename;
        if (!rename($savedFile, $target)) {
            throw new \RuntimeException('压缩包移入暂存区失败');
        }
        return $this->tempRelative($filename);
    }

    /**
     * 把（暂存区或旧平铺的）包归位到 skills/{id}/package.zip；返回最终相对路径 {id}/package.zip。
     * 若目标已存在则直接覆盖（替换旧包，不产生孤儿文件）。
     *
     * @param string $relativeSource 相对 skills 主目录的源路径（如 _tmp/xxx.zip 或 xxx.zip）
     */
    public function commit(int $skillId, string $relativeSource): ?string
    {
        $src = $this->absolute($relativeSource);
        if (!is_file($src)) {
            Log::warning('SkillStorage.commit 源包不存在: ' . $relativeSource);
            return null;
        }

        $destDir = upload_dir('skill', (string) $skillId);
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            Log::warning('SkillStorage.commit 目标目录创建失败: ' . $destDir);
            return null;
        }

        $dest = $destDir . DIRECTORY_SEPARATOR . self::PACKAGE_FILE;
        if (!rename($src, $dest)) {
            Log::warning('SkillStorage.commit 归位失败: ' . $src);
            return null;
        }

        return $skillId . '/' . self::PACKAGE_FILE;
    }

    /** 删除 Skill 后把整个 {id} 目录移入回收站（事务外调用），返回是否成功 */
    public function moveToTrash(int $skillId): bool
    {
        return move_to_trash('skill', 'skills/' . $skillId, (string) $skillId);
    }

    /** 相对 skills 主目录的路径 → 绝对路径 */
    public function absolute(string $relativePath): string
    {
        return upload_dir('skill') . DIRECTORY_SEPARATOR . ltrim($relativePath, '/\\');
    }

    /**
     * 清理超期暂存文件（暂存区仅 Skill 使用），返回统计数组
     */
    public function gcTemp(int $retentionHours): array
    {
        $dir     = $this->tempDir();
        $removed = 0;
        if (is_dir($dir)) {
            $now = time();
            foreach (scandir($dir) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $full = $dir . DIRECTORY_SEPARATOR . $name;
                if (!is_file($full)) {
                    continue;
                }
                if ($now - filemtime($full) > $retentionHours * 3600) {
                    @unlink($full);
                    $removed++;
                }
            }
        }
        return ['temp_removed' => $removed];
    }
}
