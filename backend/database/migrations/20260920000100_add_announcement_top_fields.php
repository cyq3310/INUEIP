<?php

use think\migration\Migrator;

/**
 * 公告置顶能力：新增 is_top 标记与置顶时间
 * 门户首页排序规则为「置顶优先、其次时间」：is_top DESC, topped_at DESC, published_at DESC
 */
class AddAnnouncementTopFields extends Migrator
{
    public function up(): void
    {
        $this->table('announcement')
            ->addColumn('is_top', 'integer', ['limit' => 1, 'signed' => false, 'default' => 0, 'comment' => '是否置顶 0否 1是'])
            ->addColumn('topped_at', 'datetime', ['null' => true, 'comment' => '置顶时间，取消置顶时置空'])
            ->addIndex(['status', 'is_top', 'topped_at'], ['name' => 'idx_announcement_top'])
            ->update();
    }

    public function down(): void
    {
        $this->table('announcement')
            ->removeIndex(['status', 'is_top', 'topped_at'], ['name' => 'idx_announcement_top'])
            ->removeColumn('is_top')
            ->removeColumn('topped_at')
            ->update();
    }
}
