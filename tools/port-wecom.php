<?php

$src = 'E:/my-pm/pm-server-openEuler';
$dst = 'E:/demo-website';
$root = dirname(__DIR__);

$map = [
    // 控制器
    'app/Http/Controllers/WecomAuthController.php' => 'app/Modules/Pm/Http/Controllers/WecomAuthController.php',
    'app/Http/Controllers/WecomCallbackController.php' => 'app/Modules/Pm/Http/Controllers/WecomCallbackController.php',
    'app/Http/Controllers/WecomFileController.php' => 'app/Modules/Pm/Http/Controllers/WecomFileController.php',
    'app/Http/Controllers/WecomSettingsController.php' => 'app/Modules/Pm/Http/Controllers/WecomSettingsController.php',
    'app/Http/Controllers/UserWecomController.php' => 'app/Modules/Pm/Http/Controllers/UserWecomController.php',
    // 模型
    'app/Models/WecomConfig.php' => 'app/Modules/Pm/Models/WecomConfig.php',
    'app/Models/WecomFile.php' => 'app/Modules/Pm/Models/WecomFile.php',
    'app/Models/WecomMessageLog.php' => 'app/Modules/Pm/Models/WecomMessageLog.php',
    'app/Models/WecomOauthState.php' => 'app/Modules/Pm/Models/WecomOauthState.php',
    // 服务与通知通道
    'app/Services/WecomService.php' => 'app/Modules/Pm/Services/WecomService.php',
    'app/Services/WecomNotificationService.php' => 'app/Modules/Pm/Services/WecomNotificationService.php',
    'app/Notifications/Channels/WecomChannel.php' => 'app/Modules/Pm/Notifications/Channels/WecomChannel.php',
    // 视图
    'resources/views/admin/wecom/settings.blade.php' => 'app/Modules/Pm/Resources/views/admin/wecom/settings.blade.php',
    'resources/views/profile/wecom/index.blade.php' => 'app/Modules/Pm/Resources/views/profile/wecom/index.blade.php',
    'resources/views/wecom/files/index.blade.php' => 'app/Modules/Pm/Resources/views/wecom/files/index.blade.php',
    'resources/views/wecom/partials/send-modal.blade.php' => 'app/Modules/Pm/Resources/views/wecom/partials/send-modal.blade.php',
    'resources/views/wecom/partials/send-modal-placeholder.blade.php' => 'app/Modules/Pm/Resources/views/wecom/partials/send-modal-placeholder.blade.php',
    // 配置
    'config/wecom.php' => 'config/wecom.php',
    // 迁移
    'database/migrations/2026_07_17_100002_create_wecom_configs_table.php' => 'app/Modules/Pm/Database/Migrations/2026_11_28_100001_create_pm_wecom_configs_table.php',
    'database/migrations/2026_07_17_100003_create_wecom_message_logs_table.php' => 'app/Modules/Pm/Database/Migrations/2026_11_28_100002_create_pm_wecom_message_logs_table.php',
    'database/migrations/2026_07_17_100004_create_wecom_oauth_states_table.php' => 'app/Modules/Pm/Database/Migrations/2026_11_28_100003_create_pm_wecom_oauth_states_table.php',
    'database/migrations/2026_07_17_163347_create_wecom_files_table.php' => 'app/Modules/Pm/Database/Migrations/2026_11_28_100004_create_pm_wecom_files_table.php',
    'database/migrations/2026_09_18_000001_add_wecom_fields_to_users_table.php' => 'app/Modules/Pm/Database/Migrations/2026_11_28_100005_add_wecom_fields_to_pm_users_table.php',
];

$replace = [
    'namespace App\\Http\\Controllers;' => 'namespace App\\Modules\\Pm\\Http\\Controllers;',
    'namespace App\\Models;' => 'namespace App\\Modules\\Pm\\Models;',
    'namespace App\\Services;' => 'namespace App\\Modules\\Pm\\Services;',
    'namespace App\\Notifications\\Channels;' => 'namespace App\\Modules\\Pm\\Notifications\\Channels;',
    'use App\\Models\\' => 'use App\\Modules\\Pm\\Models\\',
    'use App\\Services\\' => 'use App\\Modules\\Pm\\Services\\',
    '\\App\\Models\\' => '\\App\\Modules\\Pm\\Models\\',
    "route('profile.wecom." => "route('pm.profile.wecom.",
    "route('wecom.file." => "route('pm.wecom.file.",
    "route('wecom." => "route('pm.wecom.",
    "route('admin.wecom." => "route('pm.admin.wecom.",
    "view('admin.wecom." => "view('pm::admin.wecom.",
    "view('profile.wecom." => "view('pm::profile.wecom.",
    "view('wecom." => "view('pm::wecom.",
    "@include('wecom." => "@include('pm::wecom.",
    "@extends('layouts.app')" => "@extends('pm::layouts.app')",
    "url('/wecom/callback/event')" => "route('pm.wecom.event-callback')",
];

$n = 0;
foreach ($map as $from => $to) {
    $s = file_get_contents($src . '/' . $from);
    if ($s === false) {
        echo "read fail: {$from}\n";
        continue;
    }
    if (str_ends_with($from, '.php') && str_contains($from, 'Migrations')) {
        // 迁移类名无命名空间，仅需改表名避免与其它产品冲突
        $s = str_replace("'wecom_configs'", "'pm_wecom_configs'", $s);
        $s = str_replace("'wecom_message_logs'", "'pm_wecom_message_logs'", $s);
        $s = str_replace("'wecom_oauth_states'", "'pm_wecom_oauth_states'", $s);
        $s = str_replace("'wecom_files'", "'pm_wecom_files'", $s);
        $s = str_replace('dropIfExists(\'pm_wecom', 'dropIfExists(\'pm_wecom', $s);
    }
    foreach ($replace as $a => $b) {
        $s = str_replace($a, $b, $s);
    }
    $target = $root . '/' . $to;
    $dir = dirname($target);
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($target, $s);
    $n++;
    echo "ported: {$to}\n";
}
echo "done {$n}\n";
