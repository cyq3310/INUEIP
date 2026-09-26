<?php

use think\migration\Migrator;

/**
 * RBAC 权限体系五表：用户 / 角色 / 权限 / 用户-角色 / 角色-权限
 */
class CreateRbacTables extends Migrator
{
    public function up(): void
    {
        $innodb = ['engine' => 'InnoDB', 'collation' => 'utf8mb4_general_ci'];

        // 用户表
        $this->table('user', array_merge($innodb, ['id' => false, 'primary_key' => ['id'], 'comment' => '用户表']))
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '主键'])
            ->addColumn('username', 'string', ['limit' => 50, 'comment' => '登录账号'])
            ->addColumn('password_hash', 'string', ['limit' => 255, 'comment' => '密码哈希(bcrypt)'])
            ->addColumn('nickname', 'string', ['limit' => 50, 'comment' => '显示昵称'])
            ->addColumn('avatar', 'string', ['limit' => 255, 'null' => true, 'comment' => '头像地址'])
            ->addColumn('status', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '状态 1启用 0禁用'])
            ->addColumn('last_login_at', 'datetime', ['null' => true, 'comment' => '最后登录时间'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'comment' => '软删除时间'])
            ->addIndex(['username'], ['unique' => true, 'name' => 'uk_user_username'])
            ->addIndex(['deleted_at'], ['name' => 'idx_user_deleted_at'])
            ->create();

        // 角色表
        $this->table('role', array_merge($innodb, ['id' => false, 'primary_key' => ['id'], 'comment' => '角色表']))
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '主键'])
            ->addColumn('name', 'string', ['limit' => 50, 'comment' => '角色名称'])
            ->addColumn('code', 'string', ['limit' => 50, 'comment' => '角色编码'])
            ->addColumn('description', 'string', ['limit' => 255, 'null' => true, 'comment' => '角色描述'])
            ->addColumn('status', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '状态 1启用 0禁用'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addIndex(['code'], ['unique' => true, 'name' => 'uk_role_code'])
            ->create();

        // 权限表（菜单 + 接口）
        $this->table('permission', array_merge($innodb, ['id' => false, 'primary_key' => ['id'], 'comment' => '权限表']))
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '主键'])
            ->addColumn('parent_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '父级权限ID'])
            ->addColumn('name', 'string', ['limit' => 50, 'comment' => '权限名称'])
            ->addColumn('code', 'string', ['limit' => 100, 'comment' => '权限标识'])
            ->addColumn('type', 'enum', ['values' => ['menu', 'api'], 'default' => 'menu', 'comment' => '类型 menu菜单 api接口'])
            ->addColumn('path', 'string', ['limit' => 255, 'null' => true, 'comment' => '前端路由或接口路径'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addIndex(['code'], ['unique' => true, 'name' => 'uk_permission_code'])
            ->create();

        // 用户-角色关联表
        $this->table('user_role', array_merge($innodb, ['id' => false, 'primary_key' => ['user_id', 'role_id'], 'comment' => '用户角色关联表']))
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'comment' => '用户ID'])
            ->addColumn('role_id', 'biginteger', ['signed' => false, 'comment' => '角色ID'])
            ->create();

        // 角色-权限关联表
        $this->table('role_permission', array_merge($innodb, ['id' => false, 'primary_key' => ['role_id', 'permission_id'], 'comment' => '角色权限关联表']))
            ->addColumn('role_id', 'biginteger', ['signed' => false, 'comment' => '角色ID'])
            ->addColumn('permission_id', 'biginteger', ['signed' => false, 'comment' => '权限ID'])
            ->create();
    }

    public function down(): void
    {
        $this->table('role_permission')->drop()->save();
        $this->table('user_role')->drop()->save();
        $this->table('permission')->drop()->save();
        $this->table('role')->drop()->save();
        $this->table('user')->drop()->save();
    }
}
