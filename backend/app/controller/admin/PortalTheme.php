<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\PortalTheme as PortalThemeModel;
use think\exception\ValidateException;

/**
 * 门户分区背景图配置：图片按模块分目录存放，目录与访问前缀取自 myconfig.portal_theme
 * 新增可配置区域时，只需在 MODULES 中补充模块标识与展示名
 */
class PortalTheme extends BaseController
{
    /** 模块标识 => 展示名（同时作为图片存放的子目录名） */
    private const MODULES = [
        'greeting' => '问候与统计区',
    ];

    // 模块标识为业务常量（不随环境变化，故不进 myconfig）
    // 上传限制与图片前缀取自 myconfig.upload / myconfig.portal_theme，
    // 见 app/common.php：upload_allowed_ext() / upload_max_size() / upload_prefix() / upload_dir()

    /** 配置列表（含未配置模块的默认项） */
    public function index()
    {
        $saved = PortalThemeModel::select()->column(null, 'module');

        $list = [];
        foreach (self::MODULES as $module => $name) {
            $row          = $saved[$module] ?? null;
            $list[] = [
                'module'     => $module,
                'name'       => $name,
                'image_path' => $row ? (string) $row->image_path : null,
                'opacity'    => $row ? (int) $row->opacity : 100,
            ];
        }

        return resp_ok(['list' => $list]);
    }

    /** 保存模块配置（背景图路径 + 不透明度） */
    public function save()
    {
        $module = (string) $this->request->post('module', '');
        if (!isset(self::MODULES[$module])) {
            return resp_fail('模块不存在');
        }

        $imagePath = trim((string) $this->request->post('image_path', ''));
        if (!$this->isValidImagePath($imagePath, $module)) {
            return resp_fail('背景图路径不合法');
        }

        $opacity = (int) $this->request->post('opacity', 100);
        $opacity = max(0, min(100, $opacity));

        $row = PortalThemeModel::where('module', $module)->find();
        if (!$row) {
            $row         = new PortalThemeModel();
            $row->module = $module;
        }
        $row->name       = self::MODULES[$module];
        $row->image_path = $imagePath === '' ? null : $imagePath;
        $row->opacity    = $opacity;
        $row->updated_by = $this->request->userId;
        $row->save();

        write_oplog('portal-theme.update', "更新{$row->name}背景图配置（不透明度 {$opacity}）");
        return resp_ok([], '保存成功');
    }

    /** 上传背景图到模块目录 */
    public function upload()
    {
        $module = (string) $this->request->post('module', '');
        if (!isset(self::MODULES[$module])) {
            return resp_fail('模块不存在');
        }

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

        $dir = upload_dir('portal_theme', $module);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return resp_fail('上传目录创建失败');
        }

        // 自行生成文件名：think\File 无 getSaveName()，且可避免沿用客户端原始文件名
        $ext = strtolower((string) ($file->extension() ?: $file->getOriginalExtension()));
        if (!in_array($ext, explode(',', $allowedExt), true)) {
            return resp_fail('不支持的图片格式');
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

        $path = upload_prefix('portal_theme') . '/' . $module . '/' . $filename;

        write_oplog('portal-theme.upload', "上传{$module}背景图 {$path}");
        return resp_ok(['image_path' => $path], '上传成功');
    }

    /** 列出某模块目录下当前仍存在的背景图（以文件系统为准，已删除的不返回），供后台复用历史图片 */
    public function images()
    {
        $module = (string) $this->request->get('module', '');
        if (!isset(self::MODULES[$module])) {
            return resp_fail('模块不存在');
        }

        $dir     = upload_dir('portal_theme', $module);
        $allowed = array_map('trim', explode(',', upload_allowed_ext()));
        $result  = [];

        if (is_dir($dir)) {
            foreach (scandir($dir) as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $full = $dir . DIRECTORY_SEPARATOR . $name;
                if (!is_file($full)) {
                    continue;
                }
                $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed, true)) {
                    continue;
                }
                $result[] = upload_prefix('portal_theme') . '/' . $module . '/' . $name;
            }
        }

        // 文件名含时间戳，倒序使最新上传排在前
        rsort($result);
        return resp_ok(['list' => $result]);
    }

    /** 删除某张历史背景图：把文件移入统一回收站（保留期内可恢复），不动当前配置 */
    public function deleteImage()
    {
        $module = (string) $this->request->post('module', '');
        if (!isset(self::MODULES[$module])) {
            return resp_fail('模块不存在');
        }
        $path = trim((string) $this->request->post('path', ''));
        if ($path === '' || !$this->isValidImagePath($path, $module)) {
            return resp_fail('背景图路径不合法');
        }

        $filename = basename($path);
        $src      = upload_dir('portal_theme', $module) . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($src)) {
            return resp_fail('文件不存在或已被删除');
        }

        if (!move_to_trash('portal', 'portal/' . $module . '/' . $filename, $module)) {
            return resp_fail('删除失败，请稍后重试');
        }

        write_oplog('portal-theme.delete-image', "删除{$module}背景图 {$path}");
        return resp_ok([], '已删除');
    }

    /** 背景图路径必须位于该模块的上传目录内，为空表示清除配置 */
    private function isValidImagePath(string $path, string $module): bool
    {
        if ($path === '') {
            return true;
        }
        $prefix = preg_quote(upload_prefix('portal_theme'), '#');
        return preg_match('#^' . $prefix . '/' . preg_quote($module, '#') . '/[\w\-./]+$#', $path) === 1;
    }
}
