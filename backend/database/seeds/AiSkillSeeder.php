<?php

use think\facade\Db;
use think\migration\Seeder;

/**
 * AI Skill 平台初始数据：权限码、职能/类型分类、示例 Skill
 *
 * 权限码与前端后台菜单对齐：
 * - ai-skill:manage-all → /admin/ai-skills（全量管理菜单，同时作为接口级判定依据）
 * - ai-skill:category   → /admin/ai-skill-categories（分类管理菜单）
 *
 * 幂等：权限按 code 去重、角色授权按已分配关系去重，分类与示例 Skill 仅在表为空时写入
 */
class AiSkillSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // 权限：AI Skill 管理（id 60 段，40 段已被 PortalThemeSeeder 占用）、Skill 分类（id 70 段）
        // 80 段为后补的「员工自助类」接口权限（创建/解析压缩包/点赞/统计/分类查询），归入 AI Skill 菜单下
        $permissions = [
            ['id' => 60, 'parent_id' => 0, 'name' => 'AI Skill 管理', 'code' => 'ai-skill:manage-all', 'type' => 'menu', 'path' => '/admin/ai-skills', 'sort' => 6],
            ['id' => 61, 'parent_id' => 60, 'name' => 'Skill 全量查询', 'code' => 'ai-skill:list', 'type' => 'api', 'path' => 'GET /api/skills', 'sort' => 1],
            ['id' => 62, 'parent_id' => 60, 'name' => 'Skill 编辑', 'code' => 'ai-skill:update', 'type' => 'api', 'path' => 'PUT /api/skills/:id', 'sort' => 2],
            ['id' => 63, 'parent_id' => 60, 'name' => 'Skill 删除', 'code' => 'ai-skill:delete', 'type' => 'api', 'path' => 'DELETE /api/skills/:id', 'sort' => 3],
            ['id' => 64, 'parent_id' => 60, 'name' => 'Skill 上下架', 'code' => 'ai-skill:publish', 'type' => 'api', 'path' => 'POST /api/skills/:id/status', 'sort' => 4],
            // 85-89 段：75-82 已被知识库(kb:*)占用，故自助类接口从 85 起
            ['id' => 85, 'parent_id' => 60, 'name' => 'Skill 创建', 'code' => 'ai-skill:create', 'type' => 'api', 'path' => 'POST /api/skills', 'sort' => 5],
            ['id' => 86, 'parent_id' => 60, 'name' => 'Skill 压缩包解析', 'code' => 'ai-skill:parse-zip', 'type' => 'api', 'path' => 'POST /api/skills/parse-zip', 'sort' => 6],
            ['id' => 87, 'parent_id' => 60, 'name' => 'Skill 点赞', 'code' => 'ai-skill:like', 'type' => 'api', 'path' => 'POST /api/skills/:id/like', 'sort' => 7],
            ['id' => 88, 'parent_id' => 60, 'name' => 'Skill 统计查询', 'code' => 'ai-skill:stats', 'type' => 'api', 'path' => 'GET /api/skills/stats', 'sort' => 8],
            ['id' => 89, 'parent_id' => 60, 'name' => 'Skill 分类查询（门户）', 'code' => 'ai-skill:categories', 'type' => 'api', 'path' => 'GET /api/skills/categories', 'sort' => 9],

            ['id' => 70, 'parent_id' => 0, 'name' => 'Skill 分类', 'code' => 'ai-skill:category', 'type' => 'menu', 'path' => '/admin/ai-skill-categories', 'sort' => 7],
            ['id' => 71, 'parent_id' => 70, 'name' => '分类查询', 'code' => 'skill-category:list', 'type' => 'api', 'path' => 'GET /api/admin/ai-skill-categories', 'sort' => 1],
            ['id' => 72, 'parent_id' => 70, 'name' => '分类新增', 'code' => 'skill-category:create', 'type' => 'api', 'path' => 'POST /api/admin/ai-skill-categories', 'sort' => 2],
            ['id' => 73, 'parent_id' => 70, 'name' => '分类编辑', 'code' => 'skill-category:update', 'type' => 'api', 'path' => 'PUT /api/admin/ai-skill-categories/:id', 'sort' => 3],
            ['id' => 74, 'parent_id' => 70, 'name' => '分类删除', 'code' => 'skill-category:delete', 'type' => 'api', 'path' => 'DELETE /api/admin/ai-skill-categories/:id', 'sort' => 4],
        ];
        // 平台归属：AI Skill 功能开发在 EIP 代码库内，故归入 EIP 平台（独立产品才独占页签）
        $permissions = array_map(function (array $row) {
            return $row + ['platform' => 'eip'];
        }, $permissions);

        $newPermissions = [];
        foreach ($permissions as $permission) {
            if (Db::name('permission')->where('code', $permission['code'])->find()) {
                continue;
            }
            $newPermissions[] = $permission + ['created_at' => $now, 'updated_at' => $now];
        }
        if ($newPermissions) {
            $this->table('permission')->insert($newPermissions)->saveData();
        }

        // 超管角色（role_id=1）自动获得新增权限
        $superAdminRoleId = 1;
        $permissionIds    = Db::name('permission')->whereIn('code', array_column($permissions, 'code'))->column('id');
        $assignedIds      = array_map('intval', Db::name('role_permission')->where('role_id', $superAdminRoleId)->column('permission_id'));

        $newRolePermissions = [];
        foreach ($permissionIds as $permissionId) {
            if (in_array((int) $permissionId, $assignedIds, true)) {
                continue;
            }
            $newRolePermissions[] = ['role_id' => $superAdminRoleId, 'permission_id' => (int) $permissionId];
        }
        if ($newRolePermissions) {
            $this->table('role_permission')->insert($newRolePermissions)->saveData();
        }

        // 员工自助类接口权限预授予普通用户角色，避免路由绑定权限后阻断员工自助创建/点赞/浏览
        $this->grantToRole(2, [
            'ai-skill:create',
            'ai-skill:parse-zip',
            'ai-skill:like',
            'ai-skill:stats',
            'ai-skill:categories',
        ]);

        // 分类：1-7 职能，11-15 类型
        $categories = [
            ['id' => 1, 'name' => '研发', 'dimension' => 'function', 'icon' => 'Code', 'sort' => 10],
            ['id' => 2, 'name' => '人事', 'dimension' => 'function', 'icon' => 'Users', 'sort' => 20],
            ['id' => 3, 'name' => '财务', 'dimension' => 'function', 'icon' => 'Calculator', 'sort' => 30],
            ['id' => 4, 'name' => '市场', 'dimension' => 'function', 'icon' => 'Megaphone', 'sort' => 40],
            ['id' => 5, 'name' => '法务', 'dimension' => 'function', 'icon' => 'Scale', 'sort' => 50],
            ['id' => 6, 'name' => '运营', 'dimension' => 'function', 'icon' => 'Workflow', 'sort' => 60],
            ['id' => 7, 'name' => '行政', 'dimension' => 'function', 'icon' => 'Building2', 'sort' => 70],
            ['id' => 11, 'name' => '文档处理', 'dimension' => 'type', 'icon' => 'FileText', 'sort' => 10],
            ['id' => 12, 'name' => '代码助手', 'dimension' => 'type', 'icon' => 'Code', 'sort' => 20],
            ['id' => 13, 'name' => '数据分析', 'dimension' => 'type', 'icon' => 'BarChart', 'sort' => 30],
            ['id' => 14, 'name' => '流程自动化', 'dimension' => 'type', 'icon' => 'Workflow', 'sort' => 40],
            ['id' => 15, 'name' => '知识问答', 'dimension' => 'type', 'icon' => 'BookOpen', 'sort' => 50],
        ];
        if (Db::name('ai_skill_category')->count() === 0) {
            $this->table('ai_skill_category')->insert(array_map(function (array $row) use ($now) {
                return $row + ['status' => 1, 'created_at' => $now, 'updated_at' => $now];
            }, $categories))->saveData();
        }

        // 示例 Skill：覆盖多职能/多类型与三种状态，用于验证排序、筛选与权限边界
        $skills = [
            ['需求文档自动拆解', 'FileText', '将产品需求文档拆解为可执行开发任务，自动补全验收标准与关联模块', 1, 11, 'form', 1, 1, 86],
            ['代码评审助手', 'Code', '按团队编码规范审查 PR，输出风险等级、问题定位与修改建议清单', 1, 12, 'form', 2, 1, 64],
            ['接口测试用例生成', 'ListChecks', '依据接口定义自动生成正常、边界与异常三类测试用例', 1, 12, 'zip', 1, 1, 41],
            ['技术方案模板生成', 'FileCode', '按统一模板输出背景、方案对比、灰度与回滚设计', 1, 11, 'form', 2, 0, 12],
            ['数据库变更评审', 'Database', '检查建表与索引变更是否命中团队 DDL 规范', 1, 12, 'form', 1, 1, 27],
            ['缺陷根因分析', 'Bug', '结合日志与近期变更推断缺陷的候选根因并给出验证顺序', 1, 13, 'zip', 1, 2, 9],
            ['招聘 JD 生成器', 'Users', '依据岗位与职级生成结构化 JD，对齐公司统一能力模型描述', 2, 11, 'form', 2, 1, 33],
            ['新人入职指引问答', 'BookOpen', '基于人事制度文档回答入职流程、账号与福利类高频问题', 2, 15, 'form', 1, 1, 52],
            ['费用报销合规校验', 'Calculator', '逐条比对报销单据与费用制度，标出不合规项并给出条款依据', 3, 14, 'form', 2, 1, 45],
            ['月度经营分析摘要', 'BarChart', '汇总收入、成本与费用数据，输出带同比环比的经营摘要', 3, 13, 'form', 1, 1, 38],
            ['营销文案合规审查', 'BadgeCheck', '检查对外文案中的绝对化用语与广告法风险表述', 4, 15, 'zip', 2, 1, 21],
            ['合同条款风险扫描', 'Scale', '识别付款、违约与知识产权条款中的高风险表述并给出修改建议', 5, 15, 'zip', 1, 1, 58],
            ['周报数据自动汇总', 'Workflow', '聚合多渠道运营数据，生成带同比环比结论的结构化周报初稿', 6, 11, 'form', 1, 1, 30],
            ['取数 SQL 生成器', 'Database', '用自然语言描述指标，生成符合数仓规范的 SQL 与字段口径说明', 6, 13, 'form', 2, 1, 19],
            ['会议纪要结构化', 'NotebookPen', '把会议记录整理为议题、结论、待办与责任人四段式纪要', 7, 11, 'form', 1, 1, 24],
        ];

        $paramsByType = [
            11 => [['name' => 'document', 'label' => '待处理的文档内容', 'required' => true]],
            12 => [['name' => 'code', 'label' => '待处理的代码或仓库路径', 'required' => true]],
            13 => [['name' => 'dataset', 'label' => '数据范围（库表或时间区间）', 'required' => true]],
            14 => [['name' => 'payload', 'label' => '触发流程的业务数据', 'required' => true]],
            15 => [['name' => 'question', 'label' => '要回答的问题', 'required' => true]],
        ];

        $rows = [];
        foreach ($skills as [$name, $icon, $summary, $fnId, $tyId, $source, $ownerId, $status, $likes]) {
            $rows[] = [
                'name'                 => $name,
                'icon'                 => $icon,
                'summary'              => $summary,
                'function_category_id' => $fnId,
                'type_category_id'     => $tyId,
                'content'              => $this->buildContent($name, $summary),
                'params'               => json_encode($paramsByType[$tyId] ?? [], JSON_UNESCAPED_UNICODE),
                'source'               => $source,
                'package_path'         => null,
                'owner_id'             => $ownerId,
                'status'               => $status,
                'like_count'           => $likes,
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }
        if (Db::name('ai_skill')->count() === 0) {
            $this->table('ai_skill')->insert($rows)->saveData();
        }
    }

    /** 生成结构化的提示词正文，等价于 SKILL.md 内容 */
    /** 幂等：按权限 code 为指定角色补充授权（角色不存在或已授权则跳过） */
    private function grantToRole(int $roleId, array $codes): void
    {
        if (!Db::name('role')->where('id', $roleId)->find()) {
            return;
        }

        $permissionIds = Db::name('permission')->whereIn('code', $codes)->column('id');
        $assignedIds   = array_map('intval', Db::name('role_permission')->where('role_id', $roleId)->column('permission_id'));

        $rows = [];
        foreach ($permissionIds as $permissionId) {
            if (in_array((int) $permissionId, $assignedIds, true)) {
                continue;
            }
            $rows[] = ['role_id' => $roleId, 'permission_id' => (int) $permissionId];
        }
        if ($rows) {
            $this->table('role_permission')->insert($rows)->saveData();
        }
    }

    private function buildContent(string $name, string $summary): string
    {
        return "# {$name}\n\n"
            . "## 角色\n你是企业内部「{$name}」助手，服务于提出请求的同事。\n\n"
            . "## 任务\n{$summary}。\n\n"
            . "## 工作流程\n"
            . "1. 检查入参是否完整，缺失必填项时先向使用者追问，不要猜测\n"
            . "2. 按输出模板组织结果，先给结论再给细节\n"
            . "3. 涉及制度、金额、日期的结论必须标注依据来源\n"
            . "4. 无法确认的内容明确标注「待确认」，不得编造事实\n\n"
            . "## 输出格式\n- 结论摘要：不超过 3 条\n- 详细结果：分点列出，包含依据\n- 风险与待确认事项";
    }
}
