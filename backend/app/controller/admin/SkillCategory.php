<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\AiSkill as AiSkillModel;
use app\model\AiSkillCategory as CategoryModel;

/**
 * Skill 分类管理（后台）：function=职能、type=类型两个维度
 * 已被引用的分类不允许删除，需先调整或下架其下 Skill
 */
class SkillCategory extends BaseController
{
    private const DIMENSIONS = ['function', 'type'];

    /** 分类列表（含停用项，供后台启停） */
    public function index()
    {
        $dimension = (string) $this->request->get('dimension', '');

        $query = CategoryModel::where('sort', '>=', 0);
        if (in_array($dimension, self::DIMENSIONS, true)) {
            $query->where('dimension', $dimension);
        }

        $list = $query->order('dimension')->order('sort')->order('id')->select()->toArray();
        return resp_ok($list);
    }

    /** 新增分类 */
    public function save()
    {
        $data = $this->validateData($this->request->post());
        if (is_string($data)) {
            return resp_fail($data);
        }
        if ($this->nameExists($data['name'], $data['dimension'], 0)) {
            return resp_fail('该维度下已存在同名分类');
        }

        $now                = date('Y-m-d H:i:s');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $category = CategoryModel::create($data);
        write_oplog('ai-skill-category.create', "新增 Skill 分类 {$data['name']}(ID:{$category->id})");
        return resp_ok(['id' => (int) $category->id], '新增成功');
    }

    /** 编辑分类（含启停） */
    public function update(int $id)
    {
        $category = CategoryModel::find($id);
        if (!$category) {
            return resp_fail('分类不存在或已被删除', 404);
        }

        $data = $this->validateData($this->request->put());
        if (is_string($data)) {
            return resp_fail($data);
        }
        if ($this->nameExists($data['name'], $data['dimension'], $id)) {
            return resp_fail('该维度下已存在同名分类');
        }
        if ($data['status'] === 0 && $this->inUse($id)) {
            return resp_fail('该分类下仍有已上架的 Skill，请先调整后再停用');
        }

        $data['updated_at'] = date('Y-m-d H:i:s');
        $category->save($data);

        write_oplog('ai-skill-category.update', "编辑 Skill 分类 {$data['name']}(ID:{$id})");
        return resp_ok([], '保存成功');
    }

    /** 删除分类：被引用时拒绝 */
    public function delete(int $id)
    {
        $category = CategoryModel::find($id);
        if (!$category) {
            return resp_fail('分类不存在或已被删除', 404);
        }
        if ($this->inUse($id)) {
            return resp_fail('该分类下仍有 Skill，请先调整或下架后再删除');
        }

        $category->delete();
        write_oplog('ai-skill-category.delete', "删除 Skill 分类 {$category->name}(ID:{$id})");
        return resp_ok([], '删除成功');
    }

    /** 该分类是否仍被某个 Skill 引用 */
    private function inUse(int $id): bool
    {
        return AiSkillModel::where('function_category_id', $id)
            ->whereOr('type_category_id', $id)
            ->count() > 0;
    }

    private function nameExists(string $name, string $dimension, int $excludeId): bool
    {
        return CategoryModel::where('name', $name)
            ->where('dimension', $dimension)
            ->when($excludeId > 0, function ($q) use ($excludeId) {
                $q->where('id', '<>', $excludeId);
            })
            ->count() > 0;
    }

    /**
     * 校验并规整表单数据；返回错误消息字符串或清洗后的数据数组
     */
    private function validateData(array $data)
    {
        $name      = trim((string) ($data['name'] ?? ''));
        $dimension = (string) ($data['dimension'] ?? '');

        if ($name === '' || mb_strlen($name) > 50) {
            return '分类名称必填且不超过50字';
        }
        if (!in_array($dimension, self::DIMENSIONS, true)) {
            return '维度必须为 function(职能) 或 type(类型)';
        }

        return [
            'name'      => $name,
            'dimension' => $dimension,
            'icon'      => trim((string) ($data['icon'] ?? '')) ?: 'Tag',
            'sort'      => (int) ($data['sort'] ?? 0),
            'status'    => (int) ($data['status'] ?? 1) === 1 ? 1 : 0,
        ];
    }
}
