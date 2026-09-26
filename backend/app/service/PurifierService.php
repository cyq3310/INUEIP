<?php
declare(strict_types=1);

namespace app\service;

/**
 * 富文本 XSS 白名单过滤（HTMLPurifier）
 */
class PurifierService
{
    public static function purify(string $html): string
    {
        static $purifier = null;
        if ($purifier === null) {
            $cacheDir = app()->getRuntimePath() . 'purifier';
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            $config = \HTMLPurifier_Config::createDefault();
            $config->set('Cache.SerializerPath', $cacheDir);
            $config->set('HTML.Allowed', 'p,br,strong,em,u,s,blockquote,ul,ol,li,h1,h2,h3,h4,a[href|target],img[src|alt],table,thead,tbody,tr,th,td,hr');
            $purifier = new \HTMLPurifier($config);
        }
        return $purifier->purify($html);
    }
}
