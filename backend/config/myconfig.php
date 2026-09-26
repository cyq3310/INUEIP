<?php
// +----------------------------------------------------------------------
// | INUEIP 平台唯一配置源
// +----------------------------------------------------------------------
// | 规则来源：.codebuddy/rules/统一配置与发布规范.md
// | 1. 全平台"换环境可能变"的配置只允许写在本文件，禁止散落到业务代码
// | 2. 读取方式：config('myconfig.分组.键')，如 config('myconfig.deploy.env')
// | 3. 值一律写真实值（含生产 IP/端口/密钥），因此本文件变更必须经用户确认后才可上传
// | 4. 本文件不得有任何输出，UTF-8 无 BOM
// +----------------------------------------------------------------------

return [

    // site：站点展示信息，用于页脚、关于页、后台标题、页面 title
    'site' => [
        'name'      => '吟游廊 EIP平台',  // 站点名称，纯展示
        'version'   => '1.0.0',           // 当前版本号，随发布递增
        'copyright' => 'INUEIP',          // 版权主体，页脚展示
        'icp'       => '',                // 备案号，留空则不展示；正式环境上线前补齐
    ],

    // deploy：部署环境。env=local|staging|production，其余键随 env 变化
    'deploy' => [
        // 环境标识：local 本地开发 / staging 预发 / production 正式。切换环境只改此值及其下游键
        'env' => 'local',

        // api：后端接口自身的对外地址，用于回调地址、绝对链接拼接、定时任务
        'api' => [
            'scheme'   => 'http',                    // http|https，正式环境必须为 https
            'host'     => '127.0.0.1',               // 域名或 IP，不含 scheme 与端口
            'port'     => 8000,                      // 对外端口；https 默认 443，http 默认 80
            'base_url' => 'http://127.0.0.1:8000/api', // 接口根地址；留空则由前端按同源使用 /api
            'timeout'  => 15,                        // 接口请求超时（秒），下发前端作为 axios timeout
        ],

        // web：前端站点对外地址，用于分享链接、邮件链接、CORS 来源
        'web' => [
            'scheme'   => 'http',                    // http|https
            'host'     => '127.0.0.1',               // 域名或 IP
            'port'     => 5173,                      // 前端对外端口
            'base_url' => 'http://127.0.0.1:5173',   // 前端根地址，与 cors.allow_origin 保持一致
        ],

        // resource：静态资源来源。mode=local 走站点自身路径；mode=cdn 用 base_url 作为根地址
        'resource' => [
            'mode'     => 'local',                   // local|cdn
            'base_url' => '',                        // CDN / 对象存储根地址，如 https://cdn.example.com；local 模式可留空
        ],

        // cors：跨域白名单
        'cors' => [
            'allow_origin' => 'http://127.0.0.1:5173', // 必须等于 web.base_url；正式环境禁止填 *
        ],
    ],

    // database：数据库连接真实参数（与 backend/.env 的 DB_* 保持一致，本文件为权威源）
    'database' => [
        'type'     => 'mysql',     // 数据库类型
        'hostname' => '127.0.0.1', // 主机地址或 IP
        'hostport' => 3306,        // 端口
        'database' => 'INUEIP',    // 库名
        'username' => 'root',      // 账号
        'password' => '',          // 密码（私密项，禁止进日志与接口响应）
        'prefix'   => 'inue_',     // 表前缀
        'charset'  => 'utf8mb4',   // 字符集
    ],

    // jwt：令牌配置（私密项，禁止进 frontend_public.keys）
    'jwt' => [
        'secret' => 'change-me-to-a-random-64-char-string', // 密钥，正式部署前必须更换为 64 位随机串并与 .env 同步
        'expire' => 7200,                                   // 有效期（秒）
    ],

    // upload：上传存储配置。driver=local 存本机；driver=cdn 交由远端存储，root 可为远端路径
    'upload' => [
        'driver'      => 'local', // local|cdn
        'root'        => '',      // 存储根目录绝对路径；留空表示 backend/public/uploads
        'url_prefix'  => '/uploads', // 访问 URL 前缀，与站点同源或为已配置 CDN 前缀
        'max_size'    => 5242880, // 单文件上限（字节），5MB
        'allowed_ext' => ['jpg', 'jpeg', 'png', 'webp', 'gif'], // 允许的图片扩展名
    ],

    // announcement：公告图片。数据库只存相对路径，展示时按此配置拼前缀
    'announcement' => [
        'upload_prefix'   => '/uploads/announcement', // 本地上传访问前缀
        'remote_base_url' => '',                      // CDN / 远程图床根地址；非空时优先使用
    ],

    // portal_theme：门户分区背景图，字段含义同 announcement
    'portal_theme' => [
        'upload_prefix'   => '/uploads/portal', // 本地上传访问前缀
        'remote_base_url' => '',                // CDN / 远程图床根地址；非空时优先使用
    ],

    // skill：AI Skill 文件来源。正式环境可能无本地文件，可切 remote 或 api
    'skill' => [
        'source'          => 'local',           // local|remote|api，本地有文件用 local
        'local_path'      => '/uploads/skills', // 本地文件相对路径（source=local 时生效）
        'upload_prefix'   => '/uploads/skills', // 压缩包上传访问前缀（与 local_path 保持一致）
        'remote_base_url' => '',                // 远程文件根地址（source=remote 时生效）
        'api_endpoint'    => '/api/skills',     // 接口地址（source=api 时生效）
        'use_mock'        => false,             // 后端 skill 接口已落库，置 false 走真实接口
    ],

    // portal：门户业务默认值，前端从 GET /api/config 读取，不得在前端写死
    'portal' => [
        'page_size' => 10, // 门户列表默认分页条数
    ],

    // trash：统一回收站（Skill / 公告 / 门户背景图删除时都先移入此处，由 GC 按保留期清理）
    // 关键不变量：正式目录（uploads/skills、uploads/announcement、uploads/portal）与回收站根
    // 必须位于同一文件系统，否则 rename() 退化为「复制+删除」，失去原子性。
    'trash' => [
        // 回收站根目录绝对路径；留空表示 upload.root/trash
        'root'                => '',
        // 回收站条目保留天数，超过则被 skill:gc 彻底删除（保留期内可手工恢复）
        'retention_days'      => 30,
        // 暂存目录名（位于各资源主目录下，如 uploads/skills/_tmp；解析未提交前先落此处）
        'temp_dir'            => '_tmp',
        // 暂存文件最长保留小时数（超过且仍未提交则被 skill:gc 清理）
        'temp_retention_hours' => 24,
    ],

    // frontend_public：允许下发到前端的键白名单（值为"分组.键"）。
    // 只有列入本白名单的键才会出现在 GET /api/config 响应中；database / jwt 任何键都不得加入
    'frontend_public' => [
        'keys' => [
            'site.name',
            'site.version',
            'site.copyright',
            'site.icp',
            'deploy.env',
            'deploy.api.base_url',
            'deploy.api.timeout',
            'deploy.web.base_url',
            'deploy.resource.mode',
            'deploy.resource.base_url',
            'skill.source',
            'skill.use_mock',
            'skill.local_path',
            'skill.remote_base_url',
            'portal.page_size',
        ],
    ],
];
