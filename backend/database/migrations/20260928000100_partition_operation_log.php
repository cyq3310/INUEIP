<?php

use think\facade\Db;
use think\migration\Migrator;

/**
 * 操作日志按月 RANGE 分区（配合 oplog:maintain 做滚动补分区与过期清理）
 *
 * 背景：inue_operation_log 随后台写操作持续增长，单表查询越来越慢。
 * 方案：按 created_at 所在月份做 RANGE 分区，保留 180 天后由维护命令 DROP PARTITION 秒级清理。
 *
 * 注意：MySQL/MariaDB 要求「分区字段必须出现在所有唯一键中」，
 * 因此主键需由 (id) 调整为 (id, created_at)；created_at 同时改为 NOT NULL 以避免 NULL 落入首分区。
 * 写入侧 write_oplog() 无需任何改动（模型固定指向 operation_log）。
 */
class PartitionOperationLog extends Migrator
{
    /** 除历史与当月外，额外预建的未来月数 */
    private const FUTURE_MONTHS = 6;

    public function up(): void
    {
        $table = $this->tableName();

        // 已分区则跳过，保证重复执行安全
        if ($this->isPartitioned($table)) {
            return;
        }

        Db::execute(
            "ALTER TABLE `{$table}`
                MODIFY `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                DROP PRIMARY KEY,
                ADD PRIMARY KEY (`id`, `created_at`),
                ADD INDEX `idx_oplog_created` (`created_at`)"
        );

        $definitions = [];
        foreach ($this->monthsToCreate() as $month) {
            $key      = str_replace('-', '', $month);           // 202609
            $boundary = $this->nextMonthFirstDay($month);       // 2026-10-01
            $definitions[] = "PARTITION `p{$key}` VALUES LESS THAN (TO_DAYS('{$boundary}'))";
        }
        // 兜底分区：即便维护任务漏跑，新月份数据也不会写入失败（后续加分区时用 REORGANIZE 拆分）
        $definitions[] = 'PARTITION `pmax` VALUES LESS THAN MAXVALUE';

        Db::execute(
            "ALTER TABLE `{$table}` PARTITION BY RANGE (TO_DAYS(`created_at`)) (" . implode(', ', $definitions) . ')'
        );
    }

    public function down(): void
    {
        $table = $this->tableName();

        if ($this->isPartitioned($table)) {
            Db::execute("ALTER TABLE `{$table}` REMOVE PARTITIONING");
        }

        Db::execute(
            "ALTER TABLE `{$table}`
                DROP INDEX `idx_oplog_created`,
                DROP PRIMARY KEY,
                ADD PRIMARY KEY (`id`),
                MODIFY `created_at` datetime NULL DEFAULT NULL COMMENT '创建时间'"
        );
    }

    /** 带前缀的真实表名（config 中 DB_PREFIX=inue_） */
    private function tableName(): string
    {
        return (string) config('database.connections.mysql.prefix', '') . 'operation_log';
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

    /** 需建分区的月份：从最早日志所在月（无数据则当月）到未来第 N 个月 */
    private function monthsToCreate(): array
    {
        $first = Db::name('operation_log')->min('created_at');
        $start = new DateTimeImmutable($first ? date('Y-m-01', strtotime((string) $first)) : date('Y-m-01'));
        $end   = new DateTimeImmutable(date('Y-m-01', strtotime('+' . self::FUTURE_MONTHS . ' months')));

        $months = [];
        for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('first day of next month')) {
            $months[] = $cursor->format('Y-m');
        }

        return $months;
    }

    private function nextMonthFirstDay(string $month): string
    {
        return (new DateTimeImmutable($month . '-01'))->modify('first day of next month')->format('Y-m-d');
    }
}
