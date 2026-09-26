<?php
declare(strict_types=1);

namespace app\controller;

use app\BaseController;
use app\model\AiSkill as AiSkillModel;
use app\model\AiSkillCategory as CategoryModel;
use app\model\AiSkillLike as LikeModel;
use app\model\User;
use app\service\SkillStorage;
use think\facade\Db;
use think\exception\ValidateException;

/**
 * AI Skill 平台（员工侧 + 管理侧共用）
 *
 * 可见范围规则：
 * - 已上架(status=1)全员可见
 * - 草稿/下架仅作者本人可见
 * - 拥有 ai-skill:manage-all 权限者可查看与操作全量
 *
 * 点赞：ai_skill_like(skill_id,user_id) 唯一，计数冗余在 ai_skill.like_count，事务内维护
 */
class Skill extends BaseController
{
    /** 全量管理权限码（与前端后台菜单 ai-skill:manage-all 对齐） */
    private const MANAGE_PERM = 'ai-skill:manage-all';

    /** 压缩包限制（业务常量，不随环境变化，故不进 myconfig） */
    private const ZIP_MAX_SIZE    = 50 * 1024 * 1024; // 50MB
    private const ZIP_MAX_ENTRIES = 50;

    private const SORTS    = ['latest', 'newest', 'name', 'likes'];
    private const STATUSES = [0, 1, 2];
    private const SOURCES  = ['form', 'zip'];

    // ---------- 列表 / 统计 / 分类 ----------

    /** Skill 列表：广场(status=1)、我的 Skill(owner_id=本人)、后台全量 */
    public function index()
    {
        $page    = max(1, (int) $this->request->get('page', 1));
        // 上限 500：广场的职能计数与后台的覆盖职能统计会一次性拉取全量
        $size    = min(500, max(1, (int) $this->request->get('size', 12)));
        $keyword = trim((string) $this->request->get('keyword', ''));
        $sort    = (string) $this->request->get('sort', 'latest');
        if (!in_array($sort, self::SORTS, true)) {
            $sort = 'latest';
        }

        $uid       = (int) $this->request->userId;
        $canManage = $this->canManage();

        $statusRaw = $this->request->get('status', '');
        $status    = ($statusRaw === '' || $statusRaw === null) ? null : (int) $statusRaw;
        $ownerId   = (int) ($this->request->get('owner_id', 0) ?: 0);

        $query = AiSkillModel::where('status', '>=', 0);

        // 非管理员：草稿/下架只允许本人查看，其余情况只看已上架 + 自己的
        if (!$canManage) {
            if ($status !== null && $status !== 1) {
                $query->where('owner_id', $uid);
            } else {
                $query->where(function ($q) use ($uid) {
                    $q->where('status', 1)->whereOr('owner_id', $uid);
                });
            }
        }

        if ($status !== null && in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }
        if ($ownerId > 0) {
            $query->where('owner_id', $ownerId);
        }

        $fnId = (int) ($this->request->get('function_category_id', 0) ?: 0);
        if ($fnId > 0) {
            $query->where('function_category_id', $fnId);
        }
        $tyId = (int) ($this->request->get('type_category_id', 0) ?: 0);
        if ($tyId > 0) {
            $query->where('type_category_id', $tyId);
        }
        if ($keyword !== '') {
            $query->whereLike('name|summary|content', '%' . $keyword . '%');
        }

        if ($sort === 'likes') {
            $query->order('like_count', 'desc')->order('updated_at', 'desc');
        } elseif ($sort === 'name') {
            $query->order('name', 'asc');
        } elseif ($sort === 'newest') {
            // 最新提交：按创建时间倒序；同一秒内按 id 兜底，保证顺序稳定
            $query->order('created_at', 'desc')->order('id', 'desc');
        } else {
            $query->order('updated_at', 'desc');
        }

        $paginator = $query->paginate(['list_rows' => $size, 'page' => $page]);

        return resp_ok([
            'total' => $paginator->total(),
            'list'  => $this->decorate($paginator->items(), $uid),
        ]);
    }

    /** 统计：传 owner_id 为个人维度，不传为全平台维度（非管理员强制个人维度） */
    public function stats()
    {
        $uid     = (int) $this->request->userId;
        $ownerId = (int) ($this->request->get('owner_id', 0) ?: 0);
        if (!$this->canManage() && $ownerId !== $uid) {
            $ownerId = $uid;
        }

        $count = function (?int $status) use ($ownerId): int {
            $q = AiSkillModel::where('status', '>=', 0);
            if ($ownerId > 0) {
                $q->where('owner_id', $ownerId);
            }
            if ($status !== null) {
                $q->where('status', $status);
            }
            return $q->count();
        };

        return resp_ok([
            'total'     => $count(null),
            'published' => $count(1),
            'draft'     => $count(0),
            'offline'   => $count(2),
        ]);
    }

    /** 分类列表：管理员返回全部（含停用），普通用户仅返回启用项 */
    public function categories()
    {
        $dimension = (string) $this->request->get('dimension', '');

        $query = CategoryModel::where('sort', '>=', 0);
        if (in_array($dimension, ['function', 'type'], true)) {
            $query->where('dimension', $dimension);
        }
        if (!$this->canManage()) {
            $query->where('status', 1);
        }

        $list = $query->order('sort')->order('id')->select()->toArray();
        return resp_ok($list);
    }

    // ---------- 新建 / 编辑 / 删除 / 上下架 ----------

    /** 新建 Skill（作者为当前登录用户） */
    public function save()
    {
        $data = $this->validateData($this->request->post());
        if (is_string($data)) {
            return resp_fail($data);
        }

        $now            = date('Y-m-d H:i:s');
        $data['owner_id']   = (int) $this->request->userId;
        $data['like_count'] = 0;
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $skill = AiSkillModel::create($data);

        // 压缩包为两段式落盘：解析时先落暂存区，此处拿到 ID 后归位到 skills/{id}/package.zip
        $packagePath = $data['package_path'] ?? null;
        if ($packagePath !== null && $this->isTempPackagePath($packagePath)) {
            $final = (new SkillStorage())->commit((int) $skill->id, $packagePath);
            if ($final !== null) {
                $skill->save(['package_path' => $final]);
            }
        }

        write_oplog('ai-skill.create', "上传 Skill {$data['name']}(ID:{$skill->id})");
        return resp_ok(['id' => (int) $skill->id], '保存成功');
    }

    /** 编辑 Skill：仅作者本人或全量管理员 */
    public function update(int $id)
    {
        $skill = AiSkillModel::find($id);
        if (!$skill) {
            return resp_fail('Skill 不存在或已被删除', 404);
        }
        if (!$this->canOperate($skill)) {
            return resp_fail('只能修改自己上传的 Skill', 403);
        }

        $data = $this->validateData($this->request->put());
        if (is_string($data)) {
            return resp_fail($data);
        }
        $data['updated_at'] = date('Y-m-d H:i:s');

        $skill->save($data);

        // 换包：新上传的包仍在暂存区，归位后覆盖旧 package.zip（不产生孤儿文件）
        $packagePath = $data['package_path'] ?? null;
        if ($packagePath !== null && $this->isTempPackagePath($packagePath)) {
            $final = (new SkillStorage())->commit($id, $packagePath);
            if ($final !== null) {
                $skill->save(['package_path' => $final]);
            }
        }

        write_oplog('ai-skill.update', "编辑 Skill {$data['name']}(ID:{$id})");
        return resp_ok([], '保存成功');
    }

    /** 删除 Skill：仅作者本人或全量管理员；同时清理点赞记录 */
    public function delete(int $id)
    {
        $skill = AiSkillModel::find($id);
        if (!$skill) {
            return resp_fail('Skill 不存在或已被删除', 404);
        }
        if (!$this->canOperate($skill)) {
            return resp_fail('只能删除自己上传的 Skill', 403);
        }

        Db::transaction(function () use ($id) {
            LikeModel::where('skill_id', $id)->delete();
            AiSkillModel::destroy($id);
        });

        // 事务提交后再移动磁盘目录：文件操作在事务外，回滚时磁盘不受影响。
        // 删除失败仅记日志，不阻断接口（软删除数据已成功）。
        (new SkillStorage())->moveToTrash($id);

        write_oplog('ai-skill.delete', "删除 Skill {$skill->name}(ID:{$id})");
        return resp_ok([], '删除成功');
    }

    /** 上下架 / 存草稿：仅作者本人或全量管理员 */
    public function setStatus(int $id)
    {
        $skill = AiSkillModel::find($id);
        if (!$skill) {
            return resp_fail('Skill 不存在或已被删除', 404);
        }
        if (!$this->canOperate($skill)) {
            return resp_fail('只能操作自己上传的 Skill', 403);
        }

        $status = (int) $this->request->post('status', 1);
        if (!in_array($status, self::STATUSES, true)) {
            return resp_fail('状态值不合法');
        }

        $skill->save(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
        write_oplog('ai-skill.status', "Skill {$skill->name}(ID:{$id}) 状态改为 {$status}");
        return resp_ok([], $status === 1 ? '已上架' : '已下架');
    }

    // ---------- 点赞 ----------

    /**
     * 点赞 / 取消点赞：同一用户重复点击即在两种状态间翻转；
     * 计数与记录在同一事务内维护，返回服务端权威结果
     */
    public function like(int $id)
    {
        $uid   = (int) $this->request->userId;
        $skill = AiSkillModel::find($id);
        if (!$skill) {
            return resp_fail('Skill 不存在或已被删除', 404);
        }
        if ((int) $skill->status !== 1 && (int) $skill->owner_id !== $uid && !$this->canManage()) {
            return resp_fail('该 Skill 未上架，无法点赞', 403);
        }

        $result = Db::transaction(function () use ($id, $uid) {
            $exist = LikeModel::where('skill_id', $id)->where('user_id', $uid)->find();

            if ($exist) {
                $exist->delete();
                $liked         = false;
                AiSkillModel::where('id', $id)->where('like_count', '>', 0)->dec('like_count', 1)->update();
            } else {
                LikeModel::create([
                    'skill_id'   => $id,
                    'user_id'    => $uid,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $liked = true;
                AiSkillModel::where('id', $id)->inc('like_count', 1)->update();
            }

            return [
                'like_count' => (int) AiSkillModel::where('id', $id)->value('like_count'),
                'liked'      => $liked,
            ];
        });

        return resp_ok($result, $result['liked'] ? '已点赞' : '已取消点赞');
    }

    // ---------- 压缩包解析 ----------

    /**
     * 上传并解析 zip：落盘后逐项校验目录结构，返回校验结论与可回填的草稿。
     * 校验未通过的包不保留，避免产生垃圾文件。
     */
    public function parseZip()
    {
        $file = $this->request->file('file');
        if (!$file) {
            // 超过 post_max_size 时 PHP 会直接丢弃整个请求体，此时拿不到文件
            return resp_fail('未收到压缩包。若文件较大，请检查 php.ini 的 upload_max_filesize 与 post_max_size');
        }
        if ($file instanceof \think\file\UploadedFile && !$file->isValid()) {
            return resp_fail('上传被服务器拒绝，多为超过 php.ini 的 upload_max_filesize（当前包上限 50MB）');
        }

        try {
            validate(
                ['file' => 'fileExt:zip|fileSize:' . self::ZIP_MAX_SIZE],
                [
                    'file.fileExt'  => '仅支持 .zip 格式的压缩包',
                    'file.fileSize' => '压缩包体积不能超过 ' . (self::ZIP_MAX_SIZE / 1048576) . 'MB',
                ]
            )->check(['file' => $file]);
        } catch (ValidateException $e) {
            return resp_fail($e->getMessage());
        }

        if (!class_exists(\ZipArchive::class)) {
            return resp_fail('服务端未安装 zip 扩展，无法解析压缩包');
        }

        $storage = new SkillStorage();
        $tempDir = $storage->tempDir();
        if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
            return resp_fail('上传目录创建失败');
        }

        $filename = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.zip';
        try {
            $file->move($tempDir, $filename);
        } catch (\Throwable $e) {
            return resp_fail('上传失败：' . $e->getMessage());
        }
        $saved = $tempDir . DIRECTORY_SEPARATOR . $filename;

        $result = $this->inspectZip($saved, (string) $file->getOriginalName());
        // 校验通过返回暂存区相对路径（提交时由 save 归位）；不过则不保留，避免垃圾文件
        $result['package_path'] = $result['valid'] ? $storage->tempRelative($filename) : null;

        if (!$result['valid']) {
            @unlink($saved);
        }

        return resp_ok($result, $result['valid'] ? '校验通过' : '校验未通过');
    }

    /** 逐项校验压缩包结构并生成草稿 */
    private function inspectZip(string $file, string $originName): array
    {
        $size    = (int) filesize($file);
        $entries = [];
        $names   = [];

        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) {
            $zip->close();
            return $this->zipResult($originName, $size, [], [
                $this->check('压缩包可正常解压', true, false, '文件已损坏或不是有效的 zip 包'),
            ], null);
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat || !isset($stat['name'])) {
                continue;
            }
            // 统一路径分隔符：Windows 打包工具会写入反斜杠，部分工具会带 ./ 或 / 前缀
            $name      = $this->normalizeEntryName((string) $stat['name']);
            $isDir     = substr($name, -1) === '/';
            $entries[] = ['path' => $name, 'type' => $isDir ? 'dir' : 'file'];
            if (!$isDir) {
                $names[] = $name;
            }
        }

        // 只要包内任意层级存在 SKILL.md 即认可（按文件名比对，忽略大小写）
        $skillMd = null;
        foreach ($names as $name) {
            if (strtolower(basename($name)) === 'skill.md') {
                $skillMd = $name;
                break;
            }
        }
        $content = $skillMd !== null ? (string) $zip->getFromName($skillMd) : '';
        if ($content === '' && $skillMd !== null) {
            // getFromName 依赖原始条目名，反斜杠路径可能取不到，退化为按索引读取
            $content = $this->readByIndex($zip, $skillMd);
        }
        $zip->close();

        $hasScripts   = $this->hasDir($names, 'scripts');
        $hasResources = $this->hasDir($names, 'resources');
        $fileCount    = count($names);
        $base         = pathinfo($originName, PATHINFO_FILENAME);

        $checks = [
            $this->check(
                '包内包含 SKILL.md',
                true,
                $skillMd !== null,
                $skillMd !== null
                    ? "已找到提示词主文件 {$skillMd}"
                    : '未找到 SKILL.md。包内实际文件：' . ($names === [] ? '（空包）' : implode('、', array_slice($names, 0, 5)))
            ),
            $this->check(
                'scripts/ 目录（可选）',
                false,
                $hasScripts,
                $hasScripts ? '检测到脚本目录，将随包一起发布' : '未检测到脚本，将以纯提示词方式运行'
            ),
            $this->check(
                'resources/ 目录（可选）',
                false,
                $hasResources,
                $hasResources ? '检测到资源目录' : '未检测到参考资源'
            ),
            $this->check(
                '文件数量不超过 ' . self::ZIP_MAX_ENTRIES . ' 个',
                true,
                $fileCount <= self::ZIP_MAX_ENTRIES,
                "实际 {$fileCount} 个"
            ),
            $this->check(
                '单包体积不超过 ' . (self::ZIP_MAX_SIZE / 1048576) . 'MB',
                true,
                $size <= self::ZIP_MAX_SIZE,
                sprintf('%.2f MB', $size / 1048576)
            ),
        ];

        return $this->zipResult($originName, $size, $entries, $checks, $content !== '' ? $content : null, $base);
    }

    /** 组织解析结果（含从 SKILL.md 提取的草稿） */
    private function zipResult(
        string $fileName,
        int $size,
        array $entries,
        array $checks,
        ?string $content,
        string $base = ''
    ): array {
        $valid = true;
        foreach ($checks as $c) {
            if ($c['required'] && !$c['passed']) {
                $valid = false;
            }
        }

        $body = $content ?? '';
        $name = $base;
        if ($body !== '' && preg_match('/^#\s+(.+)$/m', $body, $m) === 1) {
            $name = trim($m[1]);
        }

        $summary = '由压缩包解析生成，请确认一句话描述';
        foreach (explode("\n", $body) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $summary = mb_substr($line, 0, 60);
            break;
        }

        return [
            'valid'        => $valid,
            'file_name'    => $fileName,
            'size'         => $size,
            'entries'      => $entries,
            'checks'       => $checks,
            'package_path' => null,
            'draft'        => [
                'name'    => $name !== '' ? $name : '未命名 Skill',
                'summary' => $summary,
                'content' => $body,
                'params'  => $this->extractParams($body),
                'icon'    => 'Package',
            ],
        ];
    }

    /** 从正文中提取 {{param}} 占位符作为运行入参 */
    private function extractParams(string $body): array
    {
        if ($body === '' || preg_match_all('/\{\{\s*([a-zA-Z_]\w*)\s*\}\}/', $body, $m) < 1) {
            return [];
        }
        $params = [];
        foreach (array_unique($m[1]) as $name) {
            $params[] = ['name' => $name, 'label' => $name, 'required' => true];
        }
        return $params;
    }

    /** 判断某逻辑目录是否存在于包内（任意层级） */
    private function hasDir(array $names, string $dir): bool
    {
        foreach ($names as $name) {
            if (preg_match('#(^|/)' . preg_quote($dir, '#') . '/#i', $name) === 1) {
                return true;
            }
        }
        return false;
    }

    /** 统一包内路径：分隔符归一为 /，去掉 ./ 与 / 前缀 */
    private function normalizeEntryName(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        while (str_starts_with($name, './')) {
            $name = substr($name, 2);
        }
        return ltrim($name, '/');
    }

    /** 按归一化后的路径回读文件内容（getFromName 依赖原始条目名，反斜杠路径会取不到） */
    private function readByIndex(\ZipArchive $zip, string $target): string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat || !isset($stat['name'])) {
                continue;
            }
            if ($this->normalizeEntryName((string) $stat['name']) === $target) {
                return (string) $zip->getFromIndex($i);
            }
        }
        return '';
    }

    private function check(string $label, bool $required, bool $passed, string $message): array
    {
        return ['label' => $label, 'required' => $required, 'passed' => $passed, 'message' => $message];
    }

    // ---------- 内部工具 ----------

    /** 补齐作者名与当前用户点赞态，并规整输出字段 */
    private function decorate(array $items, int $uid): array
    {
        if ($items === []) {
            return [];
        }

        $ownerIds = [];
        $skillIds = [];
        foreach ($items as $item) {
            $ownerIds[] = (int) $item->owner_id;
            $skillIds[] = (int) $item->id;
        }

        $owners = User::whereIn('id', array_unique($ownerIds))->column('nickname', 'id');
        $liked  = LikeModel::where('user_id', $uid)
            ->whereIn('skill_id', $skillIds)
            ->column('skill_id');
        $likedMap = array_flip(array_map('intval', $liked));

        $result = [];
        foreach ($items as $item) {
            $row                 = $item->toArray();
            $row['owner_name']   = $owners[(int) $item->owner_id] ?? '未知';
            $row['liked']        = isset($likedMap[(int) $item->id]);
            $row['params']       = $item->params ?: [];
            $row['package_path'] = $item->package_path;
            $result[]            = $row;
        }
        return $result;
    }

    /** 是否拥有全量管理权限 */
    private function canManage(): bool
    {
        $profile = $this->request->userProfile ?? [];
        return !empty($profile['isSuperAdmin'])
            || in_array(self::MANAGE_PERM, $profile['permissions'] ?? [], true);
    }

    /** 是否可操作（作者本人或全量管理员） */
    private function canOperate(AiSkillModel $skill): bool
    {
        return (int) $skill->owner_id === (int) $this->request->userId || $this->canManage();
    }

    /**
     * 校验并规整提交数据；返回错误消息字符串或清洗后的数据数组
     */
    private function validateData(array $data)
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            return '名称必填且不超过100字';
        }

        $summary = trim((string) ($data['summary'] ?? ''));
        if ($summary === '' || mb_strlen($summary) > 255) {
            return '一句话描述必填且不超过255字';
        }

        $content = trim((string) ($data['content'] ?? ''));
        if ($content === '') {
            return '提示词正文（SKILL.md）必填';
        }

        $fnId = (int) ($data['function_category_id'] ?? 0);
        $tyId = (int) ($data['type_category_id'] ?? 0);
        if (!$this->categoryExists($fnId, 'function')) {
            return '职能分类不存在或已停用';
        }
        if (!$this->categoryExists($tyId, 'type')) {
            return '类型分类不存在或已停用';
        }

        $status = (int) ($data['status'] ?? 0);
        if (!in_array($status, self::STATUSES, true)) {
            $status = 0;
        }

        $source = (string) ($data['source'] ?? 'form');
        if (!in_array($source, self::SOURCES, true)) {
            $source = 'form';
        }

        $packagePath = null;
        if ($source === 'zip') {
            $packagePath = trim((string) ($data['package_path'] ?? ''));
            if ($packagePath !== '' && !$this->isValidPackagePath($packagePath)) {
                return '资源包路径不合法，请重新上传压缩包';
            }
            $packagePath = $packagePath === '' ? null : $packagePath;
        }

        return [
            'name'                 => $name,
            'icon'                 => trim((string) ($data['icon'] ?? '')) ?: 'Sparkles',
            'summary'              => $summary,
            'function_category_id' => $fnId,
            'type_category_id'     => $tyId,
            'content'              => $content,
            'params'               => $this->normalizeParams($data['params'] ?? []),
            'source'               => $source,
            'package_path'         => $packagePath,
            'status'               => $status,
        ];
    }

    /** 入参列表规整：过滤空名、统一布尔 */
    private function normalizeParams($params): array
    {
        if (!is_array($params)) {
            return [];
        }
        $result = [];
        foreach ($params as $param) {
            if (!is_array($param)) {
                continue;
            }
            $name = trim((string) ($param['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $result[] = [
                'name'     => $name,
                'label'    => trim((string) ($param['label'] ?? '')) ?: $name,
                'required' => !empty($param['required']),
            ];
        }
        return $result;
    }

    /** 分类必须存在且启用（避免归档到已停用分类） */
    private function categoryExists(int $id, string $dimension): bool
    {
        if ($id <= 0) {
            return false;
        }
        return CategoryModel::where('id', $id)
            ->where('dimension', $dimension)
            ->where('status', 1)
            ->count() > 0;
    }

    /** 资源包路径必须落在 skills 主目录内，防止路径注入 */
    private function isValidPackagePath(string $path): bool
    {
        $temp = preg_quote((string) (config('myconfig.trash.temp_dir') ?: '_tmp'), '#');
        // 暂存区未提交包（_tmp/xxx.zip）或已归位的正式包（{id}/package.zip）
        if (preg_match('#^' . $temp . '/[\w\-.]+\.zip$#', $path) === 1) {
            return true;
        }
        return preg_match('#^\d+/package\.zip$#', $path) === 1;
    }

    /** 是否为暂存区路径（_tmp/xxx.zip），用于区分「待归位的新包」与「已归位路径」 */
    private function isTempPackagePath(string $path): bool
    {
        $temp = (string) (config('myconfig.trash.temp_dir') ?: '_tmp');
        return str_starts_with($path, $temp . '/') && preg_match('#\.zip$#', $path) === 1;
    }
}
