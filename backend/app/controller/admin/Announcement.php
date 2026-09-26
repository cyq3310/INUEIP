<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Announcement as AnnouncementModel;
use app\service\PurifierService;
use think\exception\ValidateException;

class Announcement extends BaseController
{
    /** 全站最多同时置顶的公告数（业务常量，不随环境变化，故不进 myconfig） */
    private const TOP_LIMIT = 3;

    // 上传限制（扩展名、大小）与图片前缀统一取自 myconfig.upload / myconfig.announcement，
    // 见 app/common.php：upload_allowed_ext() / upload_max_size() / upload_prefix() / upload_dir()

    /** 公告列表（分页 + 状态/关键字过滤） */
    public function index()
    {
        $page    = max(1, (int) $this->request->get('page', 1));
        $size    = min(100, max(1, (int) $this->request->get('size', 10)));
        $keyword = trim((string) $this->request->get('keyword', ''));

        $query = AnnouncementModel::order('is_top', 'desc')->order('topped_at', 'desc')->order('id', 'desc');
        if ($keyword !== '') {
            $query->whereLike('title', '%' . $keyword . '%');
        }
        $status = $this->request->get('status', '');
        if ($status !== '' && in_array((int) $status, [0, 1, 2], true)) {
            $query->where('status', (int) $status);
        }
        $paginator = $query->paginate(['list_rows' => $size, 'page' => $page]);

        return resp_ok(['total' => $paginator->total(), 'list' => $paginator->items()]);
    }

    /** 新增公告（草稿或直接发布） */
    public function save()
    {
        $result = $this->validateData($this->request->post());
        if (is_string($result)) {
            return resp_fail($result);
        }

        $result['created_by'] = (int) $this->request->userId;
        if ($result['status'] === 1) {
            $result['published_at'] = date('Y-m-d H:i:s');
        }
        $announcement = AnnouncementModel::create($result);

        // 新增公告在编辑器中上传的图片先存 tmp，保存成功后迁移到「公告ID」命名的目录
        $content = $this->moveTmpImages((string) $announcement->content, (int) $announcement->id);
        if ($content !== $announcement->content) {
            $announcement->save(['content' => $content]);
        }

        write_oplog('announcement.create', "新增公告 {$result['title']}(ID:{$announcement->id})");
        return resp_ok(['id' => (int) $announcement->id], '保存成功');
    }

    /** 编辑公告 */
    public function update(int $id)
    {
        $announcement = AnnouncementModel::find($id);
        if (!$announcement) {
            return resp_fail('公告不存在', 404);
        }

        $result = $this->validateData($this->request->put());
        if (is_string($result)) {
            return resp_fail($result);
        }
        // 编辑时若为发布状态且尚未有发布时间，补记发布时间
        if ($result['status'] === 1 && empty($announcement->published_at)) {
            $result['published_at'] = date('Y-m-d H:i:s');
        }
        // 草稿/下架不占用置顶名额
        if ($result['status'] !== 1) {
            $result['is_top']    = 0;
            $result['topped_at'] = null;
        }
        // 编辑器中上传的图片迁移到「公告ID」命名的目录
        $result['content'] = $this->moveTmpImages($result['content'], $id);
        $announcement->save($result);

        write_oplog('announcement.update', "编辑公告 {$result['title']}(ID:{$id})");
        return resp_ok([], '保存成功');
    }

    /** 删除公告（软删除） */
    public function delete(int $id)
    {
        $announcement = AnnouncementModel::find($id);
        if (!$announcement) {
            return resp_fail('公告不存在', 404);
        }
        $announcement->delete();

        // 软删除即把该公告的图片目录整体移入统一回收站（事务外；文件缺失不阻断删除）
        move_to_trash('announcement', 'announcement/' . $id, (string) $id);

        write_oplog('announcement.delete', "删除公告 {$announcement->title}(ID:{$id})");
        return resp_ok([], '删除成功');
    }

    /** 发布 */
    public function publish(int $id)
    {
        $announcement = AnnouncementModel::find($id);
        if (!$announcement) {
            return resp_fail('公告不存在', 404);
        }
        $announcement->save(['status' => 1, 'published_at' => date('Y-m-d H:i:s')]);

        write_oplog('announcement.publish', "发布公告 {$announcement->title}(ID:{$id})");
        return resp_ok([], '已发布');
    }

    /** 下架 */
    public function offline(int $id)
    {
        $announcement = AnnouncementModel::find($id);
        if (!$announcement) {
            return resp_fail('公告不存在', 404);
        }
        // 下架后不再占用置顶名额
        $announcement->save(['status' => 2, 'is_top' => 0, 'topped_at' => null]);

        write_oplog('announcement.offline', "下架公告 {$announcement->title}(ID:{$id})");
        return resp_ok([], '已下架');
    }

    /**
     * 置顶 / 取消置顶（is_top：1 置顶，0 取消）；仅已发布公告可置顶，全站最多 TOP_LIMIT 条
     */
    public function top(int $id)
    {
        $announcement = AnnouncementModel::find($id);
        if (!$announcement) {
            return resp_fail('公告不存在', 404);
        }

        $isTop = (int) $this->request->post('is_top', 1) === 1 ? 1 : 0;
        if ($isTop === 1) {
            if ((int) $announcement->status !== 1) {
                return resp_fail('仅已发布的公告可以置顶');
            }
            $topped = AnnouncementModel::where('status', 1)
                ->where('is_top', 1)
                ->where('id', '<>', $id)
                ->count();
            if ($topped >= self::TOP_LIMIT) {
                return resp_fail('最多只能置顶 ' . self::TOP_LIMIT . ' 条公告');
            }
            $announcement->save(['is_top' => 1, 'topped_at' => date('Y-m-d H:i:s')]);

            write_oplog('announcement.top', "置顶公告 {$announcement->title}(ID:{$id})");
            return resp_ok([], '已置顶');
        }

        $announcement->save(['is_top' => 0, 'topped_at' => null]);

        write_oplog('announcement.top.cancel', "取消置顶公告 {$announcement->title}(ID:{$id})");
        return resp_ok([], '已取消置顶');
    }

    /**
     * 上传公告正文图片：已保存公告存 uploads/announcement/{id}/，新建未保存的先存 tmp，保存时再迁移
     */
    public function upload()
    {
        $id   = max(0, (int) $this->request->post('announcement_id', 0));
        $file = $this->request->file('file');
        if (!$file) {
            return resp_fail('请选择要上传的图片');
        }

        $allowedExt = upload_allowed_ext();
        $maxSize    = upload_max_size();

        try {
            validate(
                ['file' => 'fileExt:' . $allowedExt . '|fileSize:' . $maxSize],
                [
                    'file.fileExt'  => '仅支持 ' . $allowedExt . ' 格式',
                    'file.fileSize' => '图片大小不能超过 ' . (int) ceil($maxSize / 1048576) . 'MB',
                ]
            )->check(['file' => $file]);
        } catch (ValidateException $e) {
            return resp_fail($e->getMessage());
        }

        $ext = strtolower((string) ($file->extension() ?: $file->getOriginalExtension()));
        if (!in_array($ext, explode(',', $allowedExt), true)) {
            return resp_fail('不支持的图片格式');
        }

        $subDir = $id > 0 ? (string) $id : 'tmp';
        $dir    = upload_dir('announcement', $subDir);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return resp_fail('上传目录创建失败');
        }

        $filename = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        try {
            $info = $file->move($dir, $filename);
        } catch (\Throwable $e) {
            return resp_fail('上传失败：' . $e->getMessage());
        }
        if (!$info) {
            return resp_fail('上传失败：' . $file->getError());
        }

        $url = upload_prefix('announcement') . '/' . $subDir . '/' . $filename;

        write_oplog('announcement.upload', "上传公告图片 {$url}");
        return resp_ok(['url' => $url], '上传成功');
    }

    /**
     * 把正文里 tmp 目录的图片迁移到「公告ID」目录，并同步改写 img src
     */
    private function moveTmpImages(string $html, int $id): string
    {
        $tmpPrefix = upload_prefix('announcement') . '/tmp/';
        if ($id <= 0 || $html === '' || strpos($html, $tmpPrefix) === false) {
            return $html;
        }

        $tmpDir    = upload_dir('announcement', 'tmp');
        $targetDir = upload_dir('announcement', (string) $id);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            // 目录不可写时保持原样，避免正文图片地址指向不存在的目录
            return $html;
        }

        $allowed  = str_replace(',', '|', upload_allowed_ext());
        $pattern  = '#' . preg_quote($tmpPrefix, '#') . '([\w\-]+\.(?:' . $allowed . '))#i';
        $result  = preg_replace_callback($pattern, function (array $matches) use ($tmpDir, $targetDir, $id): string {
            $name   = $matches[1];
            $source = $tmpDir . DIRECTORY_SEPARATOR . $name;
            $target = $targetDir . DIRECTORY_SEPARATOR . $name;
            if (is_file($source) && !is_file($target)) {
                @rename($source, $target);
            }
            return upload_prefix('announcement') . '/' . $id . '/' . $name;
        }, $html);

        return $result ?? $html;
    }

    /** 正文图片必须存放在公告图片目录内，禁止外链等非法地址 */
    private function hasAllowedImageSrc(string $html): bool
    {
        if (stripos($html, '<img') === false) {
            return true;
        }
        if (preg_match_all('/<img\b[^>]*\bsrc\s*=\s*["\']?([^"\'\s>]+)/i', $html, $matches) === false) {
            return true;
        }
        foreach ($matches[1] as $src) {
            if (preg_match('#^' . preg_quote(upload_prefix('announcement'), '#') . '/[\w\-./]+$#', $src) !== 1) {
                return false;
            }
        }
        return true;
    }

    /**
     * 校验并规整表单数据（富文本 XSS 白名单过滤）；返回错误消息或数据数组
     */
    private function validateData(array $data)
    {
        $title   = trim((string) ($data['title'] ?? ''));
        $summary = trim((string) ($data['summary'] ?? ''));
        $status  = (int) ($data['status'] ?? 0);

        if ($title === '' || mb_strlen($title) > 32) {
            return '标题必填且不超过32个字';
        }
        if (mb_strlen($summary) > 500) {
            return '摘要不超过500字';
        }
        if (!in_array($status, [0, 1, 2], true)) {
            $status = 0;
        }

        $content = PurifierService::purify((string) ($data['content'] ?? ''));
        if (!$this->hasAllowedImageSrc($content)) {
            return '正文图片地址不合法，请重新上传图片';
        }

        return [
            'title'   => $title,
            'summary' => $summary,
            'content' => $content,
            'status'  => $status,
        ];
    }
}
