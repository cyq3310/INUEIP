<?php

use think\migration\Seeder;

/**
 * 初始数据：超管账号、默认角色、权限树、示例应用链接与公告
 * 初始账号：admin / Admin@123（超管），zhangsan / User@123（普通用户）
 */
class InitDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        // 角色
        $this->table('role')->insert([
            ['id' => 1, 'name' => '超级管理员', 'code' => 'super_admin', 'description' => '拥有全部权限', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => '普通用户', 'code' => 'user', 'description' => '仅可访问门户首页', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
        ])->saveData();

        // 权限树：4 个菜单 + 各模块接口权限
        $permissions = [
            ['id' => 1, 'parent_id' => 0, 'name' => '账号管理', 'code' => 'system:user', 'type' => 'menu', 'path' => '/admin/users', 'sort' => 1],
            ['id' => 2, 'parent_id' => 1, 'name' => '用户查询', 'code' => 'user:list', 'type' => 'api', 'path' => 'GET /api/admin/users', 'sort' => 1],
            ['id' => 3, 'parent_id' => 1, 'name' => '用户新增', 'code' => 'user:create', 'type' => 'api', 'path' => 'POST /api/admin/users', 'sort' => 2],
            ['id' => 4, 'parent_id' => 1, 'name' => '用户编辑', 'code' => 'user:update', 'type' => 'api', 'path' => 'PUT /api/admin/users/:id', 'sort' => 3],
            ['id' => 5, 'parent_id' => 1, 'name' => '用户删除', 'code' => 'user:delete', 'type' => 'api', 'path' => 'DELETE /api/admin/users/:id', 'sort' => 4],
            ['id' => 6, 'parent_id' => 1, 'name' => '重置密码', 'code' => 'user:reset-password', 'type' => 'api', 'path' => 'POST /api/admin/users/:id/reset-password', 'sort' => 5],

            ['id' => 10, 'parent_id' => 0, 'name' => '角色权限', 'code' => 'system:role', 'type' => 'menu', 'path' => '/admin/roles', 'sort' => 2],
            ['id' => 11, 'parent_id' => 10, 'name' => '角色查询', 'code' => 'role:list', 'type' => 'api', 'path' => 'GET /api/admin/roles', 'sort' => 1],
            ['id' => 12, 'parent_id' => 10, 'name' => '角色新增', 'code' => 'role:create', 'type' => 'api', 'path' => 'POST /api/admin/roles', 'sort' => 2],
            ['id' => 13, 'parent_id' => 10, 'name' => '角色编辑', 'code' => 'role:update', 'type' => 'api', 'path' => 'PUT /api/admin/roles/:id', 'sort' => 3],
            ['id' => 14, 'parent_id' => 10, 'name' => '角色删除', 'code' => 'role:delete', 'type' => 'api', 'path' => 'DELETE /api/admin/roles/:id', 'sort' => 4],
            ['id' => 15, 'parent_id' => 10, 'name' => '分配权限', 'code' => 'role:assign-permission', 'type' => 'api', 'path' => 'POST /api/admin/roles/:id/permissions', 'sort' => 5],
            ['id' => 16, 'parent_id' => 10, 'name' => '权限树查询', 'code' => 'permission:tree', 'type' => 'api', 'path' => 'GET /api/admin/permissions', 'sort' => 6],

            ['id' => 20, 'parent_id' => 0, 'name' => '应用链接', 'code' => 'system:app-link', 'type' => 'menu', 'path' => '/admin/app-links', 'sort' => 3],
            ['id' => 21, 'parent_id' => 20, 'name' => '链接查询', 'code' => 'app-link:list', 'type' => 'api', 'path' => 'GET /api/admin/app-links', 'sort' => 1],
            ['id' => 22, 'parent_id' => 20, 'name' => '链接新增', 'code' => 'app-link:create', 'type' => 'api', 'path' => 'POST /api/admin/app-links', 'sort' => 2],
            ['id' => 23, 'parent_id' => 20, 'name' => '链接编辑', 'code' => 'app-link:update', 'type' => 'api', 'path' => 'PUT /api/admin/app-links/:id', 'sort' => 3],
            ['id' => 24, 'parent_id' => 20, 'name' => '链接删除', 'code' => 'app-link:delete', 'type' => 'api', 'path' => 'DELETE /api/admin/app-links/:id', 'sort' => 4],

            ['id' => 30, 'parent_id' => 0, 'name' => '公告管理', 'code' => 'system:announcement', 'type' => 'menu', 'path' => '/admin/announcements', 'sort' => 4],
            ['id' => 31, 'parent_id' => 30, 'name' => '公告查询', 'code' => 'announcement:list', 'type' => 'api', 'path' => 'GET /api/admin/announcements', 'sort' => 1],
            ['id' => 32, 'parent_id' => 30, 'name' => '公告新增', 'code' => 'announcement:create', 'type' => 'api', 'path' => 'POST /api/admin/announcements', 'sort' => 2],
            ['id' => 33, 'parent_id' => 30, 'name' => '公告编辑', 'code' => 'announcement:update', 'type' => 'api', 'path' => 'PUT /api/admin/announcements/:id', 'sort' => 3],
            ['id' => 34, 'parent_id' => 30, 'name' => '公告删除', 'code' => 'announcement:delete', 'type' => 'api', 'path' => 'DELETE /api/admin/announcements/:id', 'sort' => 4],
            ['id' => 35, 'parent_id' => 30, 'name' => '公告发布/下架', 'code' => 'announcement:publish', 'type' => 'api', 'path' => 'POST /api/admin/announcements/:id/publish|offline', 'sort' => 5],
        ];
        $permissionRows = array_map(function (array $row) use ($now) {
            return $row + ['created_at' => $now, 'updated_at' => $now];
        }, $permissions);
        $this->table('permission')->insert($permissionRows)->saveData();

        // 超管拥有全部权限
        $rolePermissions = array_map(function (array $row) {
            return ['role_id' => 1, 'permission_id' => $row['id']];
        }, $permissions);
        $this->table('role_permission')->insert($rolePermissions)->saveData();

        // 用户：admin（超管）、zhangsan（普通用户，对应设计稿问候语）
        $this->table('user')->insert([
            ['id' => 1, 'username' => 'admin', 'password_hash' => password_hash('Admin@123', PASSWORD_BCRYPT), 'nickname' => '系统管理员', 'avatar' => null, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'username' => 'zhangsan', 'password_hash' => password_hash('User@123', PASSWORD_BCRYPT), 'nickname' => '张三', 'avatar' => null, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
        ])->saveData();
        $this->table('user_role')->insert([
            ['user_id' => 1, 'role_id' => 1],
            ['user_id' => 2, 'role_id' => 2],
        ])->saveData();

        // 应用链接示例数据（管理员预配置，可在后台管理）
        $this->table('app_link')->insert([
            ['name' => '内部Wiki', 'icon' => 'BookOpen', 'subtitle' => '企业知识库', 'url' => 'https://wiki.example.com', 'section' => 'collab', 'sort' => 1, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '审批中心', 'icon' => 'ClipboardCheck', 'subtitle' => '一站式处理所有待办审批任务', 'url' => 'https://oa.example.com/approval', 'section' => 'collab', 'sort' => 2, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '人事系统', 'icon' => 'Users', 'subtitle' => '员工信息管理与组织架构管理', 'url' => 'https://hr.example.com', 'section' => 'collab', 'sort' => 3, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '会议室预订', 'icon' => 'CalendarDays', 'subtitle' => '轻松预订会议室，查看会议室日程', 'url' => 'https://meeting.example.com', 'section' => 'collab', 'sort' => 4, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '项目管理', 'icon' => 'KanbanSquare', 'subtitle' => '高效协同管理团队项目', 'url' => 'https://project.example.com', 'section' => 'custom', 'sort' => 1, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '代码仓库', 'icon' => 'GitBranch', 'subtitle' => '企业级 Git 代码托管平台', 'url' => 'https://git.example.com', 'section' => 'custom', 'sort' => 2, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '云盘文档', 'icon' => 'FolderOpen', 'subtitle' => '团队文件云端共享与协作', 'url' => 'https://disk.example.com', 'section' => 'custom', 'sort' => 3, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '数据报表', 'icon' => 'BarChart3', 'subtitle' => '业务数据可视化分析平台', 'url' => 'https://bi.example.com', 'section' => 'custom', 'sort' => 4, 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
        ])->saveData();

        // 公告示例数据（对应设计稿）
        $this->table('announcement')->insert([
            ['title' => '系统中台架构升级计划', 'summary' => '为提升系统稳定性与扩展性，中台架构将于本月进行分阶段升级', 'content' => '<p>为提升系统稳定性与扩展性，中台架构将于本月进行分阶段升级，请各部门提前做好业务验证准备。</p>', 'status' => 1, 'published_at' => '2024-05-20 09:00:00', 'created_by' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['title' => '新版本发布：v2.3.1', 'summary' => '本次更新包含多项功能优化与体验改进，详情见更新日志', 'content' => '<p>本次更新包含多项功能优化与体验改进，详情见更新日志。</p>', 'status' => 1, 'published_at' => '2024-05-18 10:00:00', 'created_by' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['title' => '五一劳动节放假通知', 'summary' => '根据国家法定节假日安排，结合公司实际情况，放假安排如下', 'content' => '<p>根据国家法定节假日安排，结合公司实际情况，放假安排如下：5月1日至5月5日放假调休，共5天。</p>', 'status' => 1, 'published_at' => '2024-04-28 14:00:00', 'created_by' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['title' => '信息安全意识培训通知', 'summary' => '全员需完成年度信息安全培训并通过考核', 'content' => '<p>全员需于本月底前完成年度信息安全培训并通过考核。</p>', 'status' => 1, 'published_at' => '2024-04-25 11:00:00', 'created_by' => 1, 'created_at' => $now, 'updated_at' => $now],
        ])->saveData();
    }
}
