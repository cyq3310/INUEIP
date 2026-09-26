<?php
declare(strict_types=1);

namespace app\command;

use app\model\AiSkill as AiSkillModel;
use app\service\SkillStorage;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * skill:migrate-packages —— 一次性历史数据迁移（幂等，可重复执行）
 *
 * 旧结构：压缩包平铺在 uploads/skills/{ts}_{hex}.zip，数据库 package_path 存 URL 式路径
 * 新结构：一个 Skill 对应 uploads/skills/{id}/package.zip，package_path 存相对路径 {id}/package.zip
 *
 * 行为：
 *  - 已是新结构（^\d+/package\.zip$）的行跳过
 *  - 旧平铺行：把文件归位到 {id}/package.zip 并回写 package_path
 *  - 源文件缺失：仅把路径规整为新结构并告警（避免重复误判）
 *  - 未被任何记录引用的孤立平铺包：移入统一回收站（不直接删）
 */
class SkillMigratePackages extends Command
{
    protected function configure()
    {
        $this->setName('skill:migrate-packages')
            ->setDescription('一次性迁移历史平铺 zip 到 skills/{id}/package.zip（幂等），孤儿文件移入回收站');
    }

    protected function execute(Input $input, Output $output)
    {
        $storage   = new SkillStorage();
        $baseDir   = upload_dir('skill');
        $tempName  = (string) (config('myconfig.trash.temp_dir') ?: '_tmp');

        $rows = AiSkillModel::where('status', '>=', 0)
            ->whereNotNull('package_path')
            ->where('package_path', '<>', '')
            ->select();

        $referenced = [];
        $migrated   = 0;
        $skipped    = 0;
        $missing    = 0;

        foreach ($rows as $skill) {
            $rel = (string) $skill->package_path;

            // 已是新结构
            if (preg_match('#^\d+/package\.zip$#', $rel) === 1) {
                $skipped++;
                continue;
            }

            // 旧平铺结构：/uploads/skills/xxx.zip 或 skills/xxx.zip
            if (preg_match('#^(?:/uploads/)?skills/([\w\-.]+\.zip)$#i', $rel, $m) !== 1) {
                $skipped++;
                continue;
            }

            $filename             = $m[1];
            $referenced[$filename] = true;
            $src                  = $baseDir . DIRECTORY_SEPARATOR . $filename;

            if (!is_file($src)) {
                $missing++;
                $output->writeln("  缺失: skill#{$skill->id} 源文件 {$rel} 不存在，仅规整路径");
                $skill->save(['package_path' => $skill->id . '/package.zip']);
                continue;
            }

            $final = $storage->commit((int) $skill->id, $filename);
            if ($final !== null) {
                $skill->save(['package_path' => $final]);
                $migrated++;
                $output->writeln("  迁移: skill#{$skill->id} -> {$final}");
            } else {
                $output->writeln("  失败: skill#{$skill->id} 归位失败 {$rel}");
            }
        }

        // 孤立平铺包：未引用且不在暂存区、也不是 {id} 子目录
        $orphan = 0;
        if (is_dir($baseDir)) {
            foreach (scandir($baseDir) ?: [] as $name) {
                if ($name === '.' || $name === '..' || $name === $tempName) {
                    continue;
                }
                if (preg_match('#^[\w\-.]+\.zip$#i', $name) !== 1) {
                    continue;
                }
                if (isset($referenced[$name])) {
                    continue;
                }
                if (move_to_trash('skill', 'skills/' . $name, 'orphan')) {
                    $orphan++;
                    $output->writeln("  孤儿: {$name} 移入回收站");
                }
            }
        }

        $output->writeln(sprintf(
            '[skill:migrate-packages] 共 %d 行：迁移=%d 跳过=%d 缺失=%d 孤儿移回收站=%d',
            count($rows),
            $migrated,
            $skipped,
            $missing,
            $orphan
        ));

        return 0;
    }
}
