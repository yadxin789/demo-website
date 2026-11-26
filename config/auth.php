<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 五产品鉴权配置（HC-11：五 users 表 / 五 guard / 不做 SSO）
|--------------------------------------------------------------------------
| 关键点：guard 名 = 产品 code。
|
| 为什么五个 guard 就能做到"五个独立登录"：
|   Laravel 把登录态写在 session 里，key 形如 login_{guard}_{sha1(id)}。
|   guard 名不同 → session key 不同 → 同一浏览器可以同时登录五个产品互不干扰，
|   而 cookie 只有一个（PHPSESSID），不需要五套 cookie。
|
| 反过来，也绝不能给两个产品共用 guard：那样在一个产品里登录就等于在另一个里登录。
|
| 五个 provider 各自指向自己的模型；模型必须在自己的 $connection 上（HC-15）。
|
*/

return [

    // 默认 guard 仅为兜底；业务中间件一律显式指定 guard（GuardByProduct）
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'erp'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'erp_users'),
    ],

    'guards' => [

        'erp' => [
            'driver' => 'session',
            'provider' => 'erp_users',
        ],

        'pm' => [
            'driver' => 'session',
            'provider' => 'pm_users',
        ],

        'shop' => [
            'driver' => 'session',
            'provider' => 'shop_users',
        ],

        'mes' => [
            'driver' => 'session',
            'provider' => 'mes_users',
        ],

        'hr' => [
            'driver' => 'session',
            'provider' => 'hr_users',
        ],

    ],

    'providers' => [

        'erp_users' => [
            'driver' => 'eloquent',
            'model' => App\Modules\Erp\Models\User::class,
        ],

        'pm_users' => [
            'driver' => 'eloquent',
            'model' => App\Modules\Pm\Models\User::class,
        ],

        'shop_users' => [
            'driver' => 'eloquent',
            'model' => App\Modules\Shop\Models\User::class,
        ],

        'mes_users' => [
            'driver' => 'eloquent',
            'model' => App\Modules\Mes\Models\User::class,
        ],

        'hr_users' => [
            'driver' => 'eloquent',
            'model' => App\Modules\Hr\Models\User::class,
        ],

    ],

    /*
    | 演示站不支持"忘记密码"：没有邮件通道，也不建 password_reset_tokens 表。
    | 这里保留结构只为兼容框架内部引用，不注册任何重置路由。
    */
    'passwords' => [

        'erp_users' => ['provider' => 'erp_users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60],
        'pm_users' => ['provider' => 'pm_users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60],
        'shop_users' => ['provider' => 'shop_users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60],
        'mes_users' => ['provider' => 'mes_users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60],
        'hr_users' => ['provider' => 'hr_users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60],

    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
