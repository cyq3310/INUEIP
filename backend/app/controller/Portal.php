<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\Announcement;
use app\model\AppLink;
use app\model\AttendanceStat;
use app\model\PortalTheme;

class Portal extends BaseController
{
    /**
     * 考勤统计（占位接口：本期无真实数据时返回模拟值，后续对接真实考勤系统只替换此数据源）
     */
    public function summary()
    {
        $stat = AttendanceStat::where('user_id', $this->request->userId)
            ->where('stat_date', date('Y-m-d'))
            ->find();

        return resp_ok([
            'unclock' => $stat ? (int) $stat->unclock_count : 1,
            'late'    => $stat ? (int) $stat->late_count : 0,
            'pending' => $stat ? (int) $stat->pending_count : 6,
        ]);
    }

    /**
     * 门户首页聚合：应用链接（仅启用，按分区分组）+ 已发布公告
     * 应用链接由管理员在后台配置后才返回，天然满足"配置后才显示"
     */
    public function home()
    {
        $links   = AppLink::where('status', 1)->order('sort')->order('id')->select();
        $grouped = ['collab' => [], 'custom' => []];
        foreach ($links as $link) {
            $grouped[$link->section][] = [
                'id'       => (int) $link->id,
                'name'     => $link->name,
                'icon'     => $link->icon,
                'subtitle' => $link->subtitle,
                'url'      => $link->url,
            ];
        }

        // 排序规则：置顶优先，其次按置顶时间、发布时间倒序
        $announcements = Announcement::where('status', 1)
            ->order('is_top', 'desc')
            ->order('topped_at', 'desc')
            ->order('published_at', 'desc')
            ->limit(10)
            ->field('id,title,summary,published_at,is_top')
            ->select();

        return resp_ok([
            'links'         => $grouped,
            'announcements' => $announcements,
        ]);
    }

    /**
     * 门户公告列表（分页 + 关键字搜索）：仅已发布公告，排序 置顶 > 置顶时间 > 发布时间 倒序
     */
    public function announcements()
    {
        $page    = max(1, (int) $this->request->get('page', 1));
        $size    = min(50, max(1, (int) $this->request->get('size', 15)));
        $keyword = trim((string) $this->request->get('keyword', ''));

        $query = Announcement::where('status', 1);
        if ($keyword !== '') {
            $query->whereLike('title', '%' . $keyword . '%');
        }
        $paginator = $query->order('is_top', 'desc')
            ->order('topped_at', 'desc')
            ->order('published_at', 'desc')
            ->field('id,title,summary,published_at,is_top')
            ->paginate(['list_rows' => $size, 'page' => $page]);

        return resp_ok(['total' => $paginator->total(), 'list' => $paginator->items()]);
    }

    /**
     * 门户公告详情：返回含正文的完整公告（仅已发布可读）
     */
    public function announcementDetail(int $id)
    {
        $announcement = Announcement::where('status', 1)
            ->field('id,title,summary,content,published_at,is_top')
            ->find($id);
        if (!$announcement) {
            return resp_fail('公告不存在或已下架', 404);
        }

        return resp_ok($announcement);
    }

    /**
     * 门户分区背景图配置，按模块返回；未配置背景图的模块不返回
     */
    public function theme()
    {
        $rows = PortalTheme::whereNotNull('image_path')->where('image_path', '<>', '')->select();

        $data = [];
        foreach ($rows as $row) {
            $data[$row->module] = [
                'image_path' => (string) $row->image_path,
                'opacity'    => (int) $row->opacity,
            ];
        }

        // 强制转为对象，保证无配置时返回 {} 而非 []，前端取值类型稳定
        return resp_ok((object) $data);
    }
}
