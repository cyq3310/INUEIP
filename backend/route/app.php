<?php

use app\middleware\AuthCheck;
use app\middleware\PermissionCheck;
use think\facade\Route;

// 认证（公开接口）
Route::post('api/auth/login', 'Auth/login');

// 平台公开配置（免登录，仅下发 myconfig 白名单项）
Route::get('api/config', 'Config/publicConfig');

// 需登录的接口
Route::group('api', function () {
    Route::post('auth/logout', 'Auth/logout');
    Route::get('auth/profile', 'Auth/profile');
    // 自助修改密码：只需登录态，不绑定权限码
    Route::post('auth/change-password', 'Auth/changePassword');

    // 门户首页聚合
    Route::get('portal/summary', 'Portal/summary');
    Route::get('portal/home', 'Portal/home');
    Route::get('portal/announcements', 'Portal/announcements');
    Route::get('portal/announcements/:id', 'Portal/announcementDetail');
    Route::get('portal/theme', 'Portal/theme');

    // AI Skill 平台：广场 / 我的 Skill / 后台全量管理共用同一组接口，
    // 可见范围与操作权限由 Skill 控制器按「作者本人 + ai-skill:manage-all」判定
    // 列表(index)对全体登录用户开放；其余接口按权限码控制，员工自助类权限已预授予普通用户角色
    Route::get('skills', 'Skill/index');
    Route::get('skills/stats', 'Skill/stats')->middleware(PermissionCheck::class, 'ai-skill:stats');
    Route::get('skills/categories', 'Skill/categories')->middleware(PermissionCheck::class, 'ai-skill:categories');
    Route::post('skills', 'Skill/save')->middleware(PermissionCheck::class, 'ai-skill:create');
    Route::post('skills/parse-zip', 'Skill/parseZip')->middleware(PermissionCheck::class, 'ai-skill:parse-zip');
    Route::put('skills/:id', 'Skill/update');
    Route::delete('skills/:id', 'Skill/delete');
    Route::post('skills/:id/status', 'Skill/setStatus');
    Route::post('skills/:id/like', 'Skill/like')->middleware(PermissionCheck::class, 'ai-skill:like');

    // 后台管理（接口级权限校验）
    Route::group('admin', function () {
        Route::get('users', 'admin.User/index')->middleware(PermissionCheck::class, 'user:list');
        Route::post('users', 'admin.User/save')->middleware(PermissionCheck::class, 'user:create');
        Route::put('users/:id', 'admin.User/update')->middleware(PermissionCheck::class, 'user:update');
        Route::delete('users/:id', 'admin.User/delete')->middleware(PermissionCheck::class, 'user:delete');
        Route::post('users/:id/reset-password', 'admin.User/resetPassword')->middleware(PermissionCheck::class, 'user:reset-password');

        Route::get('roles', 'admin.Role/index')->middleware(PermissionCheck::class, 'role:list');
        Route::post('roles', 'admin.Role/save')->middleware(PermissionCheck::class, 'role:create');
        Route::put('roles/:id', 'admin.Role/update')->middleware(PermissionCheck::class, 'role:update');
        Route::delete('roles/:id', 'admin.Role/delete')->middleware(PermissionCheck::class, 'role:delete');
        Route::post('roles/:id/permissions', 'admin.Role/assignPermissions')->middleware(PermissionCheck::class, 'role:assign-permission');
        Route::get('permissions', 'admin.Permission/index')->middleware(PermissionCheck::class, 'permission:tree');

        Route::get('app-links', 'admin.AppLink/index')->middleware(PermissionCheck::class, 'app-link:list');
        Route::post('app-links', 'admin.AppLink/save')->middleware(PermissionCheck::class, 'app-link:create');
        Route::put('app-links/:id', 'admin.AppLink/update')->middleware(PermissionCheck::class, 'app-link:update');
        Route::delete('app-links/:id', 'admin.AppLink/delete')->middleware(PermissionCheck::class, 'app-link:delete');

        Route::get('announcements', 'admin.Announcement/index')->middleware(PermissionCheck::class, 'announcement:list');
        Route::post('announcements', 'admin.Announcement/save')->middleware(PermissionCheck::class, 'announcement:create');
        Route::put('announcements/:id', 'admin.Announcement/update')->middleware(PermissionCheck::class, 'announcement:update');
        Route::delete('announcements/:id', 'admin.Announcement/delete')->middleware(PermissionCheck::class, 'announcement:delete');
        Route::post('announcements/upload', 'admin.Announcement/upload')->middleware(PermissionCheck::class, 'announcement:upload');
        Route::post('announcements/:id/publish', 'admin.Announcement/publish')->middleware(PermissionCheck::class, 'announcement:publish');
        Route::post('announcements/:id/offline', 'admin.Announcement/offline')->middleware(PermissionCheck::class, 'announcement:publish');
        Route::post('announcements/:id/top', 'admin.Announcement/top')->middleware(PermissionCheck::class, 'announcement:top');

        // AI Skill 分类管理
        Route::get('ai-skill-categories', 'admin.SkillCategory/index')->middleware(PermissionCheck::class, 'ai-skill:category');
        Route::post('ai-skill-categories', 'admin.SkillCategory/save')->middleware(PermissionCheck::class, 'ai-skill:category');
        Route::put('ai-skill-categories/:id', 'admin.SkillCategory/update')->middleware(PermissionCheck::class, 'ai-skill:category');
        Route::delete('ai-skill-categories/:id', 'admin.SkillCategory/delete')->middleware(PermissionCheck::class, 'ai-skill:category');

        // 门户背景图配置（含图片上传）
        Route::get('portal-themes', 'admin.PortalTheme/index')->middleware(PermissionCheck::class, 'portal-theme:view');
        Route::post('portal-themes', 'admin.PortalTheme/save')->middleware(PermissionCheck::class, 'portal-theme:update');
        Route::post('portal-themes/upload', 'admin.PortalTheme/upload')->middleware(PermissionCheck::class, 'portal-theme:upload');
        Route::get('portal-themes/images', 'admin.PortalTheme/images')->middleware(PermissionCheck::class, 'portal-theme:view');
        Route::delete('portal-themes/images', 'admin.PortalTheme/deleteImage')->middleware(PermissionCheck::class, 'portal-theme:update');
    });
})->middleware(AuthCheck::class);
