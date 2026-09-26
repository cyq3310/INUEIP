<?php

use think\facade\Db;
use think\migration\Seeder;

/**
 * 门户内容补充数据：协作与办公补齐 3×3 共 9 个应用，公告补齐 8 条
 *
 * 幂等设计：按名称 / 标题判断记录是否已存在，已存在则跳过。
 * 因此不会覆盖管理员后台手动调整或新增的配置（例如手动录入的「禅道」链接）。
 * 新环境执行 `php think seed:run` 时，本 seeder 排在 InitDataSeeder 之后运行（按文件名升序）。
 */
class PortalContentSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // 协作与办公：在初始 4 个之外补齐至 9 个，满足首页 3×3 栅格
        $collabLinks = [
            ['name' => '会议室预订', 'icon' => 'CalendarDays', 'subtitle' => '轻松预订会议室，查看会议室日程', 'url' => 'https://meeting.example.com', 'sort' => 11],
            ['name' => '企业邮箱', 'icon' => 'Mail', 'subtitle' => '企业邮件收发与组织架构通讯录', 'url' => 'https://mail.example.com', 'sort' => 12],
            ['name' => '工单系统', 'icon' => 'Ticket', 'subtitle' => 'IT 与行政问题在线报修与进度跟踪', 'url' => 'https://ticket.example.com', 'sort' => 13],
            ['name' => '在线文档', 'icon' => 'FileText', 'subtitle' => '多人实时协作编辑与文档共享', 'url' => 'https://docs.example.com', 'sort' => 14],
            ['name' => '即时通讯', 'icon' => 'MessageSquare', 'subtitle' => '团队沟通、群组协作与消息回执', 'url' => 'https://im.example.com', 'sort' => 15],
            ['name' => '培训学习', 'icon' => 'GraduationCap', 'subtitle' => '内部课程学习与考试认证', 'url' => 'https://learn.example.com', 'sort' => 16],
        ];

        $newLinks = [];
        foreach ($collabLinks as $link) {
            if ($this->linkExists($link['name'])) {
                continue;
            }
            $newLinks[] = $link + [
                'section'    => 'collab',
                'status'     => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($newLinks) {
            $this->table('app_link')->insert($newLinks)->saveData();
        }

        // 公告与通知：在初始 4 条之外补齐至 8 条
        $announcements = [
            [
                'title'        => '年度体检安排通知',
                'summary'      => '全员年度体检将于下月启动，请各部门按批次预约',
                'content'      => '<p>全员年度体检将于下月启动，请各部门按通知批次完成预约，逾期名额将并入下一批次。</p>',
                'published_at' => '2026-09-15 09:00:00',
            ],
            [
                'title'        => '新版报销流程上线',
                'summary'      => '报销流程简化为三步，支持移动端提交与进度查询',
                'content'      => '<p>新版报销流程已上线：提交申请、主管审批、财务打款三步完成，移动端可随时查看进度。</p>',
                'published_at' => '2026-09-14 10:00:00',
            ],
            [
                'title'        => '办公网络维护通知',
                'summary'      => '本周六凌晨进行核心网络设备维护，期间外网可能短暂中断',
                'content'      => '<p>本周六 00:00-04:00 进行核心网络设备维护，期间外网访问可能短暂中断，请提前保存工作内容。</p>',
                'published_at' => '2026-09-12 15:00:00',
            ],
            [
                'title'        => '员工团建活动报名',
                'summary'      => '秋季团建活动开始报名，提供三条线路可自选',
                'content'      => '<p>秋季团建活动即日起开始报名，提供近郊徒步、海边露营、古城文化三条线路，每人限选一条。</p>',
                'published_at' => '2026-09-10 16:00:00',
            ],
        ];

        $newAnnouncements = [];
        foreach ($announcements as $item) {
            if ($this->announcementExists($item['title'])) {
                continue;
            }
            $newAnnouncements[] = $item + [
                'status'      => 1,
                'created_by'  => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }
        if ($newAnnouncements) {
            $this->table('announcement')->insert($newAnnouncements)->saveData();
        }
    }

    private function linkExists(string $name): bool
    {
        return (bool) Db::name('app_link')->where('name', $name)->find();
    }

    private function announcementExists(string $title): bool
    {
        return (bool) Db::name('announcement')->where('title', $title)->find();
    }
}
