<?php
declare(strict_types=1);

namespace app\command;

use DateTimeImmutable;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

/**
 * oplog:maintain —— 操作日志按月分区的滚动维护
 *
 *  1) 补建未来分区：确保当月起连续 N 个月的分区存在（缺失时从 pmax 兜底分区中拆分出来），
 *     保证即使到了下个月没有可用分区也不会导致 write_oplog 写入失败。
 *  2) 清理过期分区：DROP 掉「整月都早于保留期」的旧分区，秒级完成，
 *     相比 DELETE FROM ... WHERE created_at < ... 不会扫表、不留碎片。
 *
 * 幂等：重复执行不会产生重复分区；已存在的月份会跳过。
 * 用法：php think oplog:maintain [--dry-run] [--months=6] [--days=180]
 */
class OplogMaintain extends Command
{
    protected function configure()
    {
        $this->setName('oplog:maintain')
            ->setDescription('维护操作日志按月分区：补建未来分区并清理超过保留期的旧分区（支持 --dry-run）')
            ->addOption('dry-run', 'd', Option::VALUE_NONE, '只输出计划，不实际执行 DDL')
            ->addOption('months', null, Option::VALUE_OPTIONAL, '预建未来月数，默认 6', 6)
            ->addOption('days', null, Option::VALUE_OPTIONAL, '日志保留天数，默认 180', 180);
    }

    protected function execute(Input $input, Output $output)
    {
        $dry    = (bool) $input->getOption('dry-run');
        $months = max(1, (int) $input->getOption('months'));
        $days   = max(1, (int) $input->getOption('days'));
        $table  = (string) config('database.connections.mysql.prefix', '') . 'operation_log';

        if (!$this->isPartitioned($table)) {
            $output->writeln("[oplog:maintain] 表 {$table} 尚未分区，请先执行 migrate:run（PartitionOperationLog）");

            return 1;
        }

        $existing = $this->existingPartitions($table);
        $hasPmax  = in_array('pmax', $existing, true);

        // 1) 补建未来分区
        $created = [];
        $target  = new DateTimeImmutable(date('Y-m-01', strtotime('+' . $months . ' months')));
        for ($cursor = new DateTimeImmutable(date('Y-m-01')); $cursor <= $target; $cursor = $cursor->modify('first day of next month')) {
            $key = $cursor->format('Ym');
            if (in_array('p' . $key, $existing, true) || in_array('p' . $key, $created, true)) {
                continue;
            }
            $boundary = $cursor->modify('first day of next month')->format('Y-m-d');
            $created[] = 'p' . $key;
            if (!$dry) {
                if ($hasPmax) {
                    Db::execute(
                        "ALTER TABLE `{$table}` REORGANIZE PARTITION `pmax` INTO (
                            PARTITION `p{$key}` VALUES LESS THAN (TO_DAYS('{$boundary}')),
                            PARTITION `pmax` VALUES LESS THAN MAXVALUE
                        )"
                    );
                } else {
                    Db::execute("ALTER TABLE `{$table}` ADD PARTITION (PARTITION `p{$key}` VALUES LESS THAN (TO_DAYS('{$boundary}')))");
                }
            }
        }

        // 2) 清理过期分区：整月都早于保留期的才删
        $cutoff = new DateTimeImmutable('-' . $days . ' days');
        $dropped = [];
        foreach ($existing as $name) {
            if (!preg_match('/^p(\d{6})$/', $name, $matched)) {
                continue; // 跳过 pmax 等非月份分区
            }
            $monthEnd = (new DateTimeImmutable($matched[1] . '01'))
                ->modify('last day of this month')
                ->setTime(23, 59, 59);
            if ($monthEnd < $cutoff) {
                $dropped[] = $name;
                if (!$dry) {
                    Db::execute("ALTER TABLE `{$table}` DROP PARTITION `{$name}`");
                }
            }
        }

        $output->writeln(sprintf(
            '[oplog:maintain] dry_run=%s 保留天数=%d 新增分区=%s 清理分区=%s',
            $dry ? 'true' : 'false',
            $days,
            $created ? implode(',', $created) : '无',
            $dropped ? implode(',', $dropped) : '无'
        ));

        return 0;
    }

    private function isPartitioned(string $table): bool
    {
        $rows = Db::query(
            "SELECT COUNT(DISTINCT PARTITION_NAME) AS c
               FROM information_schema.PARTITIONS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND PARTITION_NAME IS NOT NULL",
            [$table]
        );

        return (int) ($rows[0]['c'] ?? 0) > 1;
    }

    private function existingPartitions(string $table): array
    {
        $rows = Db::query(
            "SELECT PARTITION_NAME
               FROM information_schema.PARTITIONS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND PARTITION_NAME IS NOT NULL
              ORDER BY PARTITION_ORDINAL_POSITION",
            [$table]
        );

        return array_map(static fn (array $row): string => (string) $row['PARTITION_NAME'], $rows);
    }
}
