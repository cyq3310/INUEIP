<?php

use think\migration\Migrator;

/**
 * AI Skill 平台三表：Skill 主表 / Skill 分类表 / Skill 点赞表
 * 点赞以 (skill_id, user_id) 唯一索引保证「每人对同一 Skill 仅计一次」，
 * 计数冗余在 ai_skill.like_count，由控制器在事务内维护，避免列表查询时聚合。
 */
class CreateAiSkillTables extends Migrator
{
    public function up(): void
    {
        $innodb = ['engine' => 'InnoDB', 'collation' => 'utf8mb4_general_ci'];

        // Skill 分类表：function=职能（研发/人事…），type=类型（文档处理/数据分析…）
        $this->table('ai_skill_category', array_merge($innodb, ['id' => false, 'primary_key' => ['id'], 'comment' => 'AI Skill 分类表']))
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '主键'])
            ->addColumn('name', 'string', ['limit' => 50, 'comment' => '分类名称'])
            ->addColumn('dimension', 'enum', ['values' => ['function', 'type'], 'comment' => '维度 function职能 type类型'])
            ->addColumn('icon', 'string', ['limit' => 100, 'null' => true, 'comment' => '图标标识(lucide)'])
            ->addColumn('sort', 'integer', ['default' => 0, 'comment' => '排序（越小越靠前）'])
            ->addColumn('status', 'integer', ['limit' => 1, 'default' => 1, 'comment' => '状态 1启用 0停用'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addIndex(['dimension', 'name'], ['unique' => true, 'name' => 'uk_ai_skill_category_dim_name'])
            ->addIndex(['dimension', 'status', 'sort'], ['name' => 'idx_ai_skill_category_list'])
            ->create();

        // Skill 主表
        $this->table('ai_skill', array_merge($innodb, ['id' => false, 'primary_key' => ['id'], 'comment' => 'AI Skill 主表']))
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '主键'])
            ->addColumn('name', 'string', ['limit' => 100, 'comment' => 'Skill 名称'])
            ->addColumn('icon', 'string', ['limit' => 100, 'default' => 'Sparkles', 'comment' => '图标标识(lucide)'])
            ->addColumn('summary', 'string', ['limit' => 255, 'null' => true, 'comment' => '一句话描述'])
            ->addColumn('function_category_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '职能分类ID'])
            ->addColumn('type_category_id', 'biginteger', ['signed' => false, 'default' => 0, 'comment' => '类型分类ID'])
            ->addColumn('content', 'text', ['limit' => 16777215, 'null' => true, 'comment' => '提示词正文(等价于 SKILL.md)'])
            ->addColumn('params', 'text', ['null' => true, 'comment' => '运行入参定义(JSON 数组)'])
            ->addColumn('source', 'enum', ['values' => ['form', 'zip'], 'default' => 'form', 'comment' => '来源 form在线表单 zip压缩包'])
            ->addColumn('package_path', 'string', ['limit' => 500, 'null' => true, 'comment' => '资源包相对路径(source=zip)'])
            ->addColumn('owner_id', 'biginteger', ['signed' => false, 'comment' => '上传者用户ID'])
            ->addColumn('status', 'integer', ['limit' => 1, 'default' => 0, 'comment' => '状态 0草稿 1已上架 2已下架'])
            ->addColumn('like_count', 'integer', ['signed' => false, 'default' => 0, 'comment' => '点赞总数(冗余计数)'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '创建时间'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'comment' => '更新时间'])
            ->addIndex(['status', 'like_count', 'updated_at'], ['name' => 'idx_ai_skill_status_hot'])
            ->addIndex(['owner_id', 'status'], ['name' => 'idx_ai_skill_owner'])
            ->addIndex(['function_category_id', 'status'], ['name' => 'idx_ai_skill_function'])
            ->create();

        // 点赞表
        $this->table('ai_skill_like', array_merge($innodb, ['id' => false, 'primary_key' => ['id'], 'comment' => 'AI Skill 点赞表']))
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'comment' => '主键'])
            ->addColumn('skill_id', 'biginteger', ['signed' => false, 'comment' => 'Skill ID'])
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'comment' => '点赞用户ID'])
            ->addColumn('created_at', 'datetime', ['null' => true, 'comment' => '点赞时间'])
            ->addIndex(['skill_id', 'user_id'], ['unique' => true, 'name' => 'uk_ai_skill_like_skill_user'])
            ->addIndex(['user_id'], ['name' => 'idx_ai_skill_like_user'])
            ->create();
    }

    public function down(): void
    {
        $this->table('ai_skill_like')->drop()->save();
        $this->table('ai_skill')->drop()->save();
        $this->table('ai_skill_category')->drop()->save();
    }
}
