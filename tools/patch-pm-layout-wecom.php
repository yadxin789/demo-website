<?php
$f = 'E:/demo-website/app/Modules/Pm/Resources/views/layouts/app.blade.php';
$s = file_get_contents($f);

$eol = str_contains($s, "\r\n") ? "\r\n" : "\n";

// 1) 侧栏：管理员「后台服务」之后补「企业微信」
$adminItem = <<<'HTML'
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('pm.admin.wecom.*') ? 'active' : '' }}" href="{{ route('pm.admin.wecom.settings') }}">
                                <i class="fab fa-weixin me-2"></i> 企业微信
                            </a>
                        </li>
HTML;
$adminItem = str_replace("\n", $eol, $adminItem);

$servicesBlock = <<<'HTML'
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('admin.services') ? 'active' : '' }}" href="{{ route('pm.admin.services') }}">
                                <i class="fa fa-server me-2"></i> 后台服务
                            </a>
                        </li>
HTML;
$servicesBlock = str_replace("\n", $eol, $servicesBlock);

if (str_contains($s, $servicesBlock)) {
    $s = str_replace($servicesBlock, $servicesBlock . $eol . $adminItem, $s);
    echo "admin sidebar: ok\n";
} else {
    echo "admin sidebar: MISS\n";
}

// 2) 侧栏：各角色「通知中心」之后补「企业微信绑定」「企微文件收件箱」
$notifyBlock = <<<'HTML'
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('notification.index') ? 'active' : '' }}" href="{{ route('pm.notification.index') }}">
                                <i class="fa fa-bell me-2"></i> 通知中心
                            </a>
                        </li>
HTML;
$notifyBlock = str_replace("\n", $eol, $notifyBlock);

$wecomUserItems = <<<'HTML'
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('pm.profile.wecom.*') ? 'active' : '' }}" href="{{ route('pm.profile.wecom.index') }}">
                                <i class="fab fa-weixin me-2"></i> 企业微信绑定
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('pm.wecom.file.*') ? 'active' : '' }}" href="{{ route('pm.wecom.file.index') }}">
                                <i class="fa fa-inbox me-2"></i> 企微文件收件箱
                            </a>
                        </li>
HTML;
$wecomUserItems = str_replace("\n", $eol, $wecomUserItems);

$cnt = substr_count($s, $notifyBlock);
$s = str_replace($notifyBlock, $notifyBlock . $eol . $wecomUserItems, $s);
echo "role sidebar: {$cnt} blocks patched\n";

// 3) 移动端 offcanvas：管理员组补「企业微信」
$deptLine = '<li class="nav-item"><a class="nav-link {{ request()->routeIs(\'admin.departments\') ? \'active\' : \'\' }}" href="{{ route(\'pm.admin.departments\') }}" style="color: #fff;"><i class="fa fa-building me-2"></i> 部门管理</a></li>';
$wecomAdminLine = '<li class="nav-item"><a class="nav-link {{ request()->routeIs(\'pm.admin.wecom.*\') ? \'active\' : \'\' }}" href="{{ route(\'pm.admin.wecom.settings\') }}" style="color: #fff;"><i class="fab fa-weixin me-2"></i> 企业微信</a></li>';
if (str_contains($s, $deptLine)) {
    $s = str_replace($deptLine, $deptLine . $eol . '                ' . $wecomAdminLine, $s);
    echo "offcanvas admin: ok\n";
} else {
    echo "offcanvas admin: MISS\n";
}

// 4) 移动端 offcanvas：通知中心之后补两项
$notifyLine = '<li class="nav-item"><a class="nav-link {{ request()->routeIs(\'notification.index\') ? \'active\' : \'\' }}" href="{{ route(\'pm.notification.index\') }}" style="color: #fff;"><i class="fa fa-bell me-2"></i> 通知中心</a></li>';
$wecomUserLines = '<li class="nav-item"><a class="nav-link {{ request()->routeIs(\'pm.profile.wecom.*\') ? \'active\' : \'\' }}" href="{{ route(\'pm.profile.wecom.index\') }}" style="color: #fff;"><i class="fab fa-weixin me-2"></i> 企业微信绑定</a></li>'
    . $eol . '                ' .
    '<li class="nav-item"><a class="nav-link {{ request()->routeIs(\'pm.wecom.file.*\') ? \'active\' : \'\' }}" href="{{ route(\'pm.wecom.file.index\') }}" style="color: #fff;"><i class="fa fa-inbox me-2"></i> 企微文件收件箱</a></li>';
if (str_contains($s, $notifyLine)) {
    $s = str_replace($notifyLine, $notifyLine . $eol . '                ' . $wecomUserLines, $s);
    echo "offcanvas notify: ok\n";
} else {
    echo "offcanvas notify: MISS\n";
}

file_put_contents($f, $s);
echo "written\n";
