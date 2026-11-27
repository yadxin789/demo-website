# 五套视觉排查（S7-08）
#
# 检查每个产品都自带一整套外观资源，且不引用别家的资产：
#   layout / 403 / 404 / 500 / 登录页 / 主题 css / vendor 目录 / favicon
#
# 用法：pwsh tools\check-visual.ps1

param(
    [string]$Root = 'E:\demo-website'
)

$ErrorActionPreference = 'Stop'

$pass = 0
$fail = 0

function Pass([string]$msg) { $script:pass++; Write-Host ("  PASS  " + $msg) -ForegroundColor Green }
function Fail([string]$msg) { $script:fail++; Write-Host ("  FAIL  " + $msg) -ForegroundColor Red }

$products = @(
    @{ Code = 'erp';  Module = 'Erp';  Css = 'assets/erp/css/bootstrap.min.css' },
    @{ Code = 'pm';   Module = 'Pm';   Css = 'assets/pm/css/bootstrap.min.css' },
    @{ Code = 'shop'; Module = 'Shop'; Css = 'assets/shop/css/shop.css' },
    @{ Code = 'mes';  Module = 'Mes';  Css = 'assets/mes/mes.css' },
    @{ Code = 'hr';   Module = 'Hr';   Css = 'assets/hr/hr.css' }
)

Write-Host "==== 五套视觉排查 ====`n"

# ---------------------------------------------------------------- layout
Write-Host '[1] layout 与登录页'
foreach ($p in $products) {
    $views = Join-Path $Root ("app\Modules\" + $p.Module + '\Resources\views')

    # 原项目布局文件名不同（ERP 用 layouts/admin），HC-21 要求保留，两者都算合格
    $candidates = @('layouts\app.blade.php', 'layouts\admin.blade.php')
    $hit = $candidates | Where-Object { Test-Path -LiteralPath (Join-Path $views $_) } | Select-Object -First 1
    if ($hit) { Pass ("{0,-5} {1}" -f $p.Code, $hit) }
    else { Fail ("{0,-5} 缺 layouts/app 或 layouts/admin" -f $p.Code) }

    $logins = @(Get-ChildItem -LiteralPath (Join-Path $views 'auth') -Filter 'login.blade.php' -ErrorAction SilentlyContinue)
    if ($logins.Count -gt 0) { Pass ("{0,-5} auth/login.blade.php" -f $p.Code) }
    else { Fail ("{0,-5} 缺 auth/login.blade.php" -f $p.Code) }
}

# ---------------------------------------------------------------- 错误页
Write-Host "`n[2] 403 / 404 / 500 错误页"
foreach ($p in $products) {
    $err = Join-Path $Root ("app\Modules\" + $p.Module + '\Resources\views\errors')
    foreach ($code in @(403, 404, 500)) {
        $f = Join-Path $err "$code.blade.php"
        if (Test-Path -LiteralPath $f) { Pass ("{0,-5} errors/{1}.blade.php" -f $p.Code, $code) }
        else { Fail ("{0,-5} 缺 errors/{1}.blade.php" -f $p.Code, $code) }
    }
}

# ---------------------------------------------------------------- 主题 css 与 vendor
Write-Host "`n[3] 主题 css 与本地 vendor"
foreach ($p in $products) {
    $css = Join-Path $Root ('public\' + ($p.Css -replace '/', '\'))
    if (Test-Path -LiteralPath $css) { Pass ("{0,-5} {1}" -f $p.Code, $p.Css) }
    else { Fail ("{0,-5} 缺 {1}" -f $p.Code, $p.Css) }

    # 本地化判定：assets/{x}/vendor 存在，或 assets/{x}/css 下自带 js/css 目录（原项目布局，HC-21）
    $vendor = Join-Path $Root ('public\assets\' + $p.Code + '\vendor')
    $ownCss = Join-Path $Root ('public\assets\' + $p.Code + '\css')
    $ownJs = Join-Path $Root ('public\assets\' + $p.Code + '\js')

    if (Test-Path -LiteralPath $vendor) {
        Pass ("{0,-5} assets/{1}/vendor 本地化" -f $p.Code, $p.Code)
    } elseif ((Test-Path -LiteralPath $ownCss) -and (Test-Path -LiteralPath $ownJs)) {
        Pass ("{0,-5} assets/{1}/css + js 本地化（沿用原项目布局）" -f $p.Code, $p.Code)
    } else {
        Fail ("{0,-5} 缺本地 css/js 或 vendor" -f $p.Code)
    }
}

# ---------------------------------------------------------------- 资产串味
Write-Host "`n[4] 资产串味（A 产品视图不得引用 B 产品资产）"
foreach ($p in $products) {
    $views = Join-Path $Root ("app\Modules\" + $p.Module + '\Resources\views')
    if (! (Test-Path -LiteralPath $views)) { Fail ("{0,-5} 视图目录不存在" -f $p.Code); continue }

    $bad = @()
    $blades = Get-ChildItem -LiteralPath $views -Recurse -Filter '*.blade.php'
    foreach ($b in $blades) {
        $text = Get-Content -LiteralPath $b.FullName -Raw
        foreach ($o in $products) {
            if ($o.Code -eq $p.Code) { continue }
            if ($text -match ('assets/' + $o.Code + '/') -or $text -match ("'" + $o.Code + "::")) {
                $bad += ($b.Name + ' → ' + $o.Code)
            }
        }
    }

    if ($bad.Count -eq 0) { Pass ("{0,-5} 视图未引用别家资产（{1} 个视图）" -f $p.Code, $blades.Count) }
    else { Fail ("{0,-5} 资产串味：" + ($bad -join '; ')) }
}

# ---------------------------------------------------------------- favicon
Write-Host "`n[5] favicon"
foreach ($p in $products) {
    $icon = Join-Path $Root ('public\assets\' + $p.Code + '\favicon.ico')
    $svg = Join-Path $Root ('public\assets\' + $p.Code + '\favicon.svg')
    if ((Test-Path -LiteralPath $icon) -or (Test-Path -LiteralPath $svg)) {
        Pass ("{0,-5} favicon 在位" -f $p.Code)
    } else {
        Write-Host ("  WARN  {0,-5} 无 assets/{1}/favicon（不阻塞，浏览器会回落默认图标）" -f $p.Code, $p.Code) -ForegroundColor Yellow
    }
}

Write-Host ''
Write-Host ("==== 汇总：PASS {0} / FAIL {1} ====" -f $pass, $fail)

exit ([int]($fail -gt 0))


