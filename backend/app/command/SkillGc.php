<?php
declare(strict_types=1);

namespace app\command;

use app\service\SkillStorage;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * skill:gc —— 统一回收站/暂存区清理
 *  - 清理 trash/ 下超过保留期的分组目录（按目录 mtime 判断，彻底删除）
 *  - 清理 skills/_tmp 下超过保留期的暂存文件
 * 支持 --dry-run 只统计不删除。
 */
class SkillGc extends Command
{
    protected function configure()
    {
        $this->setName('skill:gc')
            ->setDescription('清理统一回收站超期条目与 skill 暂存区（支持 --dry-run）')
            ->addOption('dry-run', 'd', Option::VALUE_NONE, '只统计不删除');
    }

    protected function execute(Input $input, Output $output)
    {
        $dry        = (bool) $input->getOption('dry-run');
        $trashDays  = (int) (config('myconfig.trash.retention_days') ?: 30);
        $tempHours  = (int) (config('myconfig.trash.temp_retention_hours') ?: 24);

        $trash = gc_trash($trashDays, $dry);
        $temp  = (new SkillStorage())->gcTemp($tempHours);

        $output->writeln(sprintf(
            '[skill:gc] dry_run=%s 回收站扫描=%d 将删除=%d 实际删除=%d；暂存清理=%d',
            $dry ? 'true' : 'false',
            $trash['scanned'],
            $trash['would_remove'],
            $trash['removed'],
            $temp['temp_removed']
        ));

        return 0;
    }
}
