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
            // 保留 wangEditor 的文字样式输出：行内 span + 块级元素的 style
            $config->set('HTML.Allowed', 'p[style],br,span[style],strong[style],em[style],u[style],s[style],blockquote[style],div[data-w-e-type|style],ul[style],ol[style],li[style],h1[style],h2[style],h3[style],h4[style],a[href|target|style],img[src|alt],table,thead,tbody,tr,th[style],td[style],hr,input[type|checked|disabled]');

            // style 仅放行排版类属性，禁止 position/url()/expression 等可被滥用的 CSS
            $config->set('CSS.AllowedProperties', [
                'color'            => true,
                'background-color' => true,
                'font-size'        => true,
                'font-family'      => true,
                'font-weight'      => true,
                'font-style'       => true,
                'line-height'      => true,
                'text-align'       => true,
                'text-decoration'  => true,
                'text-indent'      => true,
            ]);

            // 启用自定义定义时必须指定 DefinitionID（配合缓存序列化）；后续调整定义需递增 DefinitionRev
            $config->set('HTML.DefinitionID', 'inue-announcement');
            $config->set('HTML.DefinitionRev', 2);

            // wangEditor 待办列表输出 <div data-w-e-type="todo"><input type="checkbox" disabled>：
            // HTMLPurifier 默认模块不含 input 元素与 data-* 属性，需扩展定义后才能保留（属性限定为
            // checkbox/checked/disabled，禁止事件类属性，避免 XSS）
            $def = $config->maybeGetRawHTMLDefinition();
            if ($def) {
                $def->addElement('input', 'Inline', 'Empty', 'Common', [
                    'type'     => 'Enum#checkbox',
                    'checked'  => 'Bool#checked',
                    'disabled' => 'Bool#disabled',
                ]);
                $def->addAttribute('div', 'data-w-e-type', 'Enum#todo');
            }

            $purifier = new \HTMLPurifier($config);
        }
        return $purifier->purify($html);
    }
}
