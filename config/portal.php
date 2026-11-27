<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 门户对外信息（S7-12 / S7-13）
|--------------------------------------------------------------------------
| 联系方式、备案号、主体名称都放这里，上线前改一处即可（不要在视图里硬编码）。
| 环境变量优先，便于服务器上用 .env 覆盖而不改代码。
|
*/

return [

    // 站点主体
    'name' => env('PORTAL_NAME', '产品演示中心'),

    // 联系方式（页脚与预约表单旁展示；留空即不在页面上出现，电话按主体意愿不公开）
    'contact' => [
        'email' => env('PORTAL_CONTACT_EMAIL', 'yadxin789@163.com'),
        'wechat' => env('PORTAL_CONTACT_WECHAT', 'yadxin789'),
        'phone' => env('PORTAL_CONTACT_PHONE', ''),
        'hours' => env('PORTAL_CONTACT_HOURS', '工作日 9:00–18:00'),
    ],

    // 合规信息
    'icp' => env('PORTAL_ICP', 'ICP 备案办理中'),
    'police' => env('PORTAL_POLICE', ''),          // 公安备案号（如有）
    'copyright' => env('PORTAL_COPYRIGHT', ''),    // 版权所有者，留空则用 name
    'updated_at' => env('PORTAL_UPDATED_AT', '2026-11-27'),
];
