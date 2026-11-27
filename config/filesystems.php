<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        // PM 企业微信（企微文件收件箱）落本地，不对外暴露
        'wecom_files' => [
            'driver' => 'local',
            'root' => storage_path('app/wecom-files'),
            'throw' => false,
            'report' => false,
        ],

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // ── 以下五个盘由 Shop 模块使用，全部落本地，不进 public、不对外暴露 ──
        // HC-22：第三方/业务资源一律本地存储，禁用 CDN 与外链

        // 商品图片：由应用路由读流输出，断网环境仍可见
        'products' => [
            'driver' => 'local',
            'root' => storage_path('app/products'),
            'throw' => false,
            'report' => false,
        ],

        // 本地备份包：storage/app/backups
        'backups' => [
            'driver' => 'local',
            'root' => storage_path('app/backups'),
            'throw' => false,
            'report' => false,
        ],

        // AI 聊天附件（图片 / 文件 / ZIP 解压工作区）
        'ai_chat' => [
            'driver' => 'local',
            'root' => storage_path('app/ai-chat'),
            'throw' => false,
            'report' => false,
        ],

        // AI 文档生成资源（模板 / 范例 / 支援文件）
        'ai_docs' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
            'report' => false,
        ],

        // AI 变更提案暂存区：确认后转正式库，驳回/过期即清理
        'ai_staging' => [
            'driver' => 'local',
            'root' => storage_path('app/ai-staging'),
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
