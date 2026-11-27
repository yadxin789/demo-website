<?php

$f = 'E:/demo-website/app/Modules/Pm/Resources/views/layouts/app.blade.php';
$s = file_get_contents($f);
$eol = str_contains($s, "\r\n") ? "\r\n" : "\n";
$orig = $s;

// ---------- 1. 抽出桌面侧栏菜单本体，落到 partial ----------
$startTag = '<ul class="nav flex-column" role="navigation">';
$start = strpos($s, $startTag);
if ($start === false) {
    exit("sidebar start not found\n");
}
$endMarker = "</ul>{$eol}                </div>{$eol}            </nav>";
$end = strpos($s, $endMarker, $start);
if ($end === false) {
    exit("sidebar end not found\n");
}
$menuInner = substr($s, $start + strlen($startTag), $end - $start - strlen($startTag));
$menuBlock = $startTag . $menuInner . '</ul>';

$partialDir = 'E:/demo-website/app/Modules/Pm/Resources/views/layouts/partials';
if (! is_dir($partialDir)) {
    mkdir($partialDir, 0777, true);
}
file_put_contents($partialDir . '/sidebar-menu.blade.php', $menuBlock . $eol);

// 桌面侧栏改为 include
$s = substr($s, 0, $start) . "@include('pm::layouts.partials.sidebar-menu')" . substr($s, $end + 5);

// ---------- 2. 抽屉菜单改为同一份 partial ----------
$drawerStart = '<ul class="nav flex-column" style="font-size: 14px;">';
$ds = strpos($s, $drawerStart);
if ($ds === false) {
    exit("drawer start not found\n");
}
$drawerEndMarker = "</ul>{$eol}        </div>{$eol}    </div>";
$de = strpos($s, $drawerEndMarker, $ds);
if ($de === false) {
    exit("drawer end not found\n");
}
$drawerNew = '<div class="pm-mobile-menu">' . $eol . '                ' . "@include('pm::layouts.partials.sidebar-menu')" . $eol . '            </div>';
$s = substr($s, 0, $ds) . $drawerNew . substr($s, $de + 5);

// ---------- 3. 顶栏右侧不再折叠，保证手机也能看到铃铛与头像 ----------
$s = str_replace(
    '<div class="collapse navbar-collapse justify-content-end" id="navbarNav">',
    '<div class="navbar-collapse justify-content-end" id="navbarNav" style="display: flex !important; flex-basis: auto;">',
    $s
);

// ---------- 4. 顶栏补企业微信入口（与参考一致） ----------
$navTail = "                                </li>{$eol}{$eol}                            </ul>";
$wecomItem = "                                <li class=\"nav-item\">{$eol}"
    . "                                    <a class=\"nav-link\" href=\"{{ route('pm.profile.wecom.index') }}\" title=\"企业微信\" style=\"display: flex; align-items: center;\">{$eol}"
    . "                                        <i class=\"fab fa-weixin\" style=\"font-size: 18px;\"></i>{$eol}"
    . "                                    </a>{$eol}"
    . "                                </li>{$eol}{$eol}                            </ul>";
if (str_contains($s, $navTail)) {
    $s = str_replace($navTail, $wecomItem, $s);
    echo "navbar wecom: ok\n";
} else {
    echo "navbar wecom: MISS\n";
}

// ---------- 5. 抽屉配色与移动端顶栏细节 ----------
$cssAnchor = "<style>{$eol}";
if (str_contains($s, $cssAnchor)) {
    $append = "        /* 移动端抽屉：复用桌面同一份菜单，深底白字 */{$eol}"
        . "        #mobileSidebar .nav-link { color: #fff; padding: 10px 16px; }{$eol}"
        . "        #mobileSidebar .nav-link.active { background: rgba(255,255,255,.14); color: #fff; }{$eol}"
        . "        #mobileSidebar .badge { font-size: 11px; }{$eol}"
        . "        /* 窄屏顶栏：保留铃铛与头像下拉，隐藏冗长文字 */{$eol}"
        . "        @media (max-width: 575.98px) { .user-info .user-name, .user-info .user-role { display: none; } }{$eol}";
    $s = preg_replace('/<style>\r?\n/', "<style>{$eol}" . $append, $s, 1);
    echo "css: ok\n";
} else {
    echo "css: MISS\n";
}

file_put_contents($f, $s);
echo "layout rewritten\n";
echo "partial bytes=" . filesize($partialDir . '/sidebar-menu.blade.php') . "\n";
