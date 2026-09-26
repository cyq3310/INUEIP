<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\AppLink as AppLinkModel;

class AppLink extends BaseController
{
    private const SECTIONS = ['collab', 'custom'];

    /** 链接列表（分页 + 分区/状态/关键字过滤） */
    public function index()
    {
        $page    = max(1, (int) $this->request->get('page', 1));
        $size    = min(100, max(1, (int) $this->request->get('size', 20)));
        $section = (string) $this->request->get('section', '');
        $keyword = trim((string) $this->request->get('keyword', ''));

        $query = AppLinkModel::order('section')->order('sort')->order('id');
        if (in_array($section, self::SECTIONS, true)) {
            $query->where('section', $section);
        }
        if ($keyword !== '') {
            $query->whereLike('name|subtitle', '%' . $keyword . '%');
        }
        $status = $this->request->get('status', '');
        if ($status !== '' && in_array((int) $status, [0, 1], true)) {
            $query->where('status', (int) $status);
        }
        $paginator = $query->paginate(['list_rows' => $size, 'page' => $page]);

        return resp_ok(['total' => $paginator->total(), 'list' => $paginator->items()]);
    }

    /** 新增链接 */
    public function save()
    {
        $result = $this->validateData($this->request->post());
        if (is_string($result)) {
            return resp_fail($result);
        }

        $link = AppLinkModel::create($result);
        write_oplog('app-link.create', "新增应用链接 {$result['name']}(ID:{$link->id})");
        return resp_ok(['id' => (int) $link->id], '新增成功');
    }

    /** 编辑链接 */
    public function update(int $id)
    {
        $link = AppLinkModel::find($id);
        if (!$link) {
            return resp_fail('链接不存在', 404);
        }

        $result = $this->validateData($this->request->put());
        if (is_string($result)) {
            return resp_fail($result);
        }

        $link->save($result);
        write_oplog('app-link.update', "编辑应用链接 {$result['name']}(ID:{$id})");
        return resp_ok([], '保存成功');
    }

    /** 删除链接 */
    public function delete(int $id)
    {
        $link = AppLinkModel::find($id);
        if (!$link) {
            return resp_fail('链接不存在', 404);
        }
        $link->delete();

        write_oplog('app-link.delete', "删除应用链接 {$link->name}(ID:{$id})");
        return resp_ok([], '删除成功');
    }

    /**
     * 校验并规整表单数据；返回错误消息字符串或清洗后的数据数组
     */
    private function validateData(array $data)
    {
        $name    = trim((string) ($data['name'] ?? ''));
        $url     = trim((string) ($data['url'] ?? ''));
        $section = (string) ($data['section'] ?? '');

        if ($name === '' || mb_strlen($name) > 100) {
            return '名称必填且不超过100字';
        }
        if (!preg_match('/^https?:\/\//i', $url)) {
            return '链接地址必须以 http:// 或 https:// 开头';
        }
        if (!in_array($section, self::SECTIONS, true)) {
            return '分区必须为 collab(协作与办公) 或 custom(自定义工具)';
        }

        $status = (int) ($data['status'] ?? 1);
        return [
            'name'     => $name,
            'icon'     => trim((string) ($data['icon'] ?? '')) ?: 'Link',
            'subtitle' => trim((string) ($data['subtitle'] ?? '')),
            'url'      => $url,
            'section'  => $section,
            'sort'     => (int) ($data['sort'] ?? 0),
            'status'   => in_array($status, [0, 1], true) ? $status : 1,
        ];
    }
}
