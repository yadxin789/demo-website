<?php

/*
 * 百货零售 ERP — 业务与本地资源配置
 * 所有前端静态资源均落地 public/vendor，禁止公网 CDN 外链（离线运行）。
 */

return [

    // 应用基础信息
    'name' => '百货零售 ERP',
    'currency' => 'CNY',
    'currency_symbol' => '¥',
    // 金额小数位（存储 DECIMAL(18,4)，展示按此）
    'money_decimals' => 2,

    // 成本计价方法：standard（标准成本）；avco / fifo 为 P4 预留，本期不启用
    'cost_method' => 'standard',

    // 库存范围：单仓库多库位（跨仓在途调拨为预留，本期不开放）
    'single_warehouse' => true,

    // 本地静态资源清单（版本锁定，路径相对 public/）
    'assets' => [
        'bootstrap' => [
            'version' => '5.3.8',
            'css' => 'assets/shop/vendor/bootstrap/css/bootstrap.min.css',
            'js' => 'assets/shop/vendor/bootstrap/js/bootstrap.bundle.min.js',
        ],
        'fontawesome' => [
            'version' => '6.7.2',
            'css' => 'assets/shop/vendor/fontawesome/css/all.min.css',
            // webfonts 与 css 同级 ../webfonts/，相对引用
        ],
        'chartjs' => [
            'version' => '4.4.7',
            'js' => 'assets/shop/vendor/chartjs/chart.umd.min.js',
        ],
        'jsbarcode' => [
            'version' => '3.11.6',
            'js' => 'assets/shop/vendor/jsbarcode/JsBarcode.all.min.js',
        ],
        'qrcode' => [
            'version' => '1.0.0',
            'js' => 'assets/shop/vendor/qrcode/qrcode.min.js',
        ],
        'jsqr' => [
            'version' => '1.4.0',
            'js' => 'assets/shop/vendor/jsqr/jsQR.js',
        ],
    ],

    // 小票 / 打印（ESC/POS + 钱箱），P1-POS 细化
    'receipt' => [
        'driver' => env('RECEIPT_DRIVER', 'escpos'), // escpos | browser
        'proxy_url' => env('RECEIPT_PROXY_URL', 'http://127.0.0.1:9100'),
        'paper_width' => 58, // 58mm / 80mm
    ],

    // 审计日志：不可删除、不可修改
    'audit' => [
        'immutable' => true,
    ],

    // POS 收银
    'pos' => [
        // 挂单有效期（分钟），默认 24 小时；过期不可取单
        'saved_order_ttl_minutes' => (int) env('POS_SAVED_ORDER_TTL', 1440),
    ],

    // 库存预警
    'inventory' => [
        // 临期预警天数：距效期小于等于该天数列入临期清单
        'expiry_warning_days' => (int) env('SHOP_EXPIRY_WARNING_DAYS', 30),
    ],

    // 本地备份与还原（数据库 mysqldump + 本地磁盘文件打包为 zip）
    'backup' => [
        'disk' => 'backups',
        'mysqldump' => env('BACKUP_MYSQLDUMP', PHP_OS_FAMILY === 'Windows' ? 'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\mysqldump.exe' : 'mysqldump'),
        'mysql' => env('BACKUP_MYSQL', PHP_OS_FAMILY === 'Windows' ? 'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\mysql.exe' : 'mysql'),
        // 需要一并备份的本地磁盘（config/filesystems.php 中的 disk 名）
        'file_disks' => ['products'],
        // 备份包数量上限与单包大小上限（MB），防止磁盘被占满
        'max_files' => 50,
        'max_size_mb' => 512,
    ],
];
