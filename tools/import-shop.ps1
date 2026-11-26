# =====================================================================
# import-shop.ps1 — 把 E:\erp-shop 搬进 App\Modules\Shop
# 与 import-erp.ps1 的差异：
#   1) Shop 的 Models 是平铺结构（无子目录），User 直接覆盖 S0 精简版并注入 connection
#   2) 多两组命名空间：App\Observers\ / App\Rules\ / App\Exceptions\
#   3) Shop 自带 Role/Permission 表与 AuthorizePermission 中间件（不用 spatie），
#      路由里的 middleware('permission:x') 由 rewrite-routes 改成 shop.permission:x
#   4) 不搬 App\Console（HC-25：演示版不要定时任务）
# 可重复执行：先清空目标子目录再复制（源码只读）。
# =====================================================================
param(
    [string]$Source = 'E:\erp-shop',
    [string]$Root   = 'E:\demo-website',
    [switch]$SkipAssets,
    [switch]$SkipMigrate
)

$ErrorActionPreference = 'Stop'
$Mod  = Join-Path $Root 'app\Modules\Shop'
$Cur  = 'App\Modules\Shop'
$Code = 'shop'
$enc  = New-Object System.Text.UTF8Encoding($false)
$report = New-Object System.Collections.Generic.List[string]

function Write-Utf8([string]$Path, [string]$Text) {
    $dir = Split-Path -Parent $Path
    if (-not (Test-Path -LiteralPath $dir)) { New-Item -ItemType Directory -Force -Path $dir | Out-Null }
    [System.IO.File]::WriteAllText($Path, $Text, $enc)
}
function Read-Utf8([string]$Path) {
    return [System.IO.File]::ReadAllText($Path, [System.Text.Encoding]::UTF8)
}

Write-Output '==== 1/9 清理目标子目录 ===='
foreach ($sub in @('Ai','Enums','Exceptions','Http','Observers','Policies','Rules','Services','Support','Database')) {
    $p = Join-Path $Mod $sub
    if (Test-Path -LiteralPath $p) { Remove-Item -LiteralPath $p -Recurse -Force; $report.Add("clean: $sub") }
}
$modelsDir = Join-Path $Mod 'Models'
if (-not (Test-Path -LiteralPath $modelsDir)) { New-Item -ItemType Directory -Force -Path $modelsDir | Out-Null }
Get-ChildItem $modelsDir -File -Filter *.php -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -ne 'BaseModel.php' } | ForEach-Object { Remove-Item -LiteralPath $_.FullName -Force }
$viewsDir = Join-Path $Mod 'Resources\views'
if (Test-Path -LiteralPath $viewsDir) { Remove-Item -LiteralPath $viewsDir -Recurse -Force }

Write-Output '==== 2/9 复制 app 各层（不含 Console）===='
foreach ($sub in @('Ai','Enums','Exceptions','Http','Observers','Policies','Rules','Services','Support')) {
    $src = Join-Path $Source "app\$sub"
    $dst = Join-Path $Mod $sub
    if (-not (Test-Path -LiteralPath $src)) { $report.Add("skip-missing: app\$sub"); continue }
    New-Item -ItemType Directory -Force -Path $dst | Out-Null
    Copy-Item -Path (Join-Path $src '*') -Destination $dst -Recurse -Force
    $report.Add("copy: app\$sub")
}
# Models：平铺复制，BaseModel.php 由 S0 维护
Copy-Item -Path (Join-Path $Source 'app\Models\*') -Destination $modelsDir -Recurse -Force
$report.Add("copy: app\Models（$(Get-ChildItem $modelsDir -File -Filter *.php).Count 个）")

$srcConsole = Join-Path $Source 'app\Console'
if (Test-Path $srcConsole) {
    $n = @(Get-ChildItem $srcConsole -Recurse -File -Filter *.php).Count
    $report.Add("drop: app\Console（$n 个命令，HC-25）")
}

Write-Output '==== 3/9 命名空间改写 ===='
$nsMap = [ordered]@{
    'App\Http\'       = "$Cur\Http\"
    'App\Models\'     = "$Cur\Models\"
    'App\Services\'   = "$Cur\Services\"
    'App\Policies\'   = "$Cur\Policies\"
    'App\Support\'    = "$Cur\Support\"
    'App\Enums\'      = "$Cur\Enums\"
    'App\Ai\'         = "$Cur\Ai\"
    'App\Observers\'  = "$Cur\Observers\"
    'App\Rules\'      = "$Cur\Rules\"
    'App\Exceptions\' = "$Cur\Exceptions\"
}
$files = Get-ChildItem $Mod -Recurse -File -Include *.php,*.blade.php -ErrorAction SilentlyContinue
$changed = 0
foreach ($f in $files) {
    $raw = Read-Utf8 $f.FullName
    if ($null -eq $raw) { continue }
    $new = $raw
    foreach ($k in $nsMap.Keys) { $new = $new.Replace($k, $nsMap[$k]) }
    if ($new -ne $raw) { Write-Utf8 $f.FullName $new; $changed++ }
}
Write-Output "  改写 $changed 个文件的命名空间"

Write-Output '==== 4/9 模型基类与 User 连接 ===='
$modelFiles = Get-ChildItem $modelsDir -File -Filter *.php | Where-Object { $_.Name -ne 'BaseModel.php' }
$mChanged = 0
foreach ($m in $modelFiles) {
    $raw = Read-Utf8 $m.FullName
    if ($null -eq $raw) { continue }
    $new = $raw -replace 'extends\s+Model(\s|$)', 'extends BaseModel$1'
    if ($m.Name -eq 'User.php') {
        # User 是 Authenticatable，必须显式指向本产品连接与 guard，否则会落进 erp 库
        if ($new -notmatch "protected\s+`$connection") {
            $new = $new -replace '(class\s+User\s+extends\s+Authenticatable\s*\{)', "`$1`r`n`r`n    protected `$connection = '$Code';`r`n`r`n    protected `$guard_name = '$Code';`r`n"
        }
    }
    if ($new -ne $raw) { Write-Utf8 $m.FullName $new; $mChanged++ }
}
Write-Output "  改基类/连接 $mChanged 个模型"

Write-Output '==== 5/9 视图搬迁与引用改写 ===='
$srcViews = Join-Path $Source 'resources\views'
New-Item -ItemType Directory -Force -Path $viewsDir | Out-Null
Copy-Item -Path (Join-Path $srcViews '*') -Destination $viewsDir -Recurse -Force
$blades = Get-ChildItem $viewsDir -Recurse -File -Filter *.blade.php
$vChanged = 0
foreach ($b in $blades) {
    $raw = Read-Utf8 $b.FullName
    if ($null -eq $raw) { continue }
    $new = $raw
    $new = $new -replace "(@extends\(\s*')", "`$1$Code::"
    $new = $new -replace "(@include\(\s*')", "`$1$Code::"
    $new = $new -replace "(@includeIf\(\s*')", "`$1$Code::"
    $new = $new -replace "(@each\(\s*')", "`$1$Code::"
    $new = $new -replace "(@component\(\s*')", "`$1$Code::"
    $new = $new -replace "(view\(\s*')", "`$1$Code::"
    foreach ($k in $nsMap.Keys) { $new = $new.Replace($k, $nsMap[$k]) }
    if ($new -ne $raw) { Write-Utf8 $b.FullName $new; $vChanged++ }
}
Write-Output "  复制 $($blades.Count) 个视图，$vChanged 个需改引用"

Write-Output '==== 6/9 迁移与种子 ===='
$mg = Join-Path $Mod 'Database\Migrations'
$srcMg = Join-Path $Source 'database\migrations'
New-Item -ItemType Directory -Force -Path $mg | Out-Null
$migCount = 0
foreach ($mi in (Get-ChildItem $srcMg -Filter *.php -File | Sort-Object Name)) {
    $raw = Read-Utf8 $mi.FullName
    $raw = $raw -replace '(extends\s+Migration\s*\{)', "`$1`r`n    protected `$connection = '$Code';`r`n"
    Write-Utf8 (Join-Path $mg $mi.Name) $raw
    $migCount++
}
Write-Output "  迁移 $migCount 个（已注入 connection=$Code）"

$sd = Join-Path $Mod 'Database\Seeders'
$srcSd = Join-Path $Source 'database\seeders'
if (Test-Path -LiteralPath $srcSd) {
    New-Item -ItemType Directory -Force -Path $sd | Out-Null
    Copy-Item -Path (Join-Path $srcSd '*') -Destination $sd -Recurse -Force
    foreach ($s in Get-ChildItem $sd -Recurse -File -Filter *.php) {
        $raw = Read-Utf8 $s.FullName
        $new = $raw
        foreach ($k in $nsMap.Keys) { $new = $new.Replace($k, $nsMap[$k]) }
        $new = $new.Replace('Database\Seeders', "$Cur\Database\Seeders")
        if ($new -ne $raw) { Write-Utf8 $s.FullName $new }
    }
    Write-Output "  种子 $((Get-ChildItem $sd -Filter *.php).Count) 个"
}
$fa = Join-Path $Mod 'Database\Factories'
$srcFa = Join-Path $Source 'database\factories'
if (Test-Path -LiteralPath $srcFa) {
    New-Item -ItemType Directory -Force -Path $fa | Out-Null
    Copy-Item -Path (Join-Path $srcFa '*') -Destination $fa -Recurse -Force
    foreach ($s in Get-ChildItem $fa -Recurse -File -Filter *.php) {
        $raw = Read-Utf8 $s.FullName
        $new = $raw.Replace('Database\Factories', "$Cur\Database\Factories")
        foreach ($k in $nsMap.Keys) { $new = $new.Replace($k, $nsMap[$k]) }
        if ($new -ne $raw) { Write-Utf8 $s.FullName $new }
    }
}

Write-Output '==== 7/9 config 复制 ===='
$cfgDir = Join-Path $Root 'config'
foreach ($c in @('shop.php','payment_gateways.php')) {
    $src = Join-Path $Source ('config\' + $c)
    if (-not (Test-Path -LiteralPath $src)) { $report.Add("skip-config: $c"); continue }
    $raw = Read-Utf8 $src
    foreach ($k in $nsMap.Keys) { $raw = $raw.Replace($k, $nsMap[$k]) }
    Write-Utf8 (Join-Path $cfgDir $c) $raw
    $report.Add("config: $c")
}
Write-Output '  已复制 Shop 专属配置到 config/'

Write-Output '==== 8/9 前端资产 ===='
if (-not $SkipAssets) {
    $pub = Join-Path $Root "public\assets\$Code"
    New-Item -ItemType Directory -Force -Path $pub | Out-Null
    foreach ($d in @('css','js','avatars','vendor')) {
        $s = Join-Path $Source "public\$d"
        if (Test-Path -LiteralPath $s) { Copy-Item -Path $s -Destination $pub -Recurse -Force; $report.Add("asset: $d") }
    }
    $fav = Join-Path $Source 'public\favicon.ico'
    if (Test-Path -LiteralPath $fav) { Copy-Item -LiteralPath $fav -Destination (Join-Path $pub 'favicon.ico') -Force }
    Write-Output "  资产已复制到 public/assets/$Code"
}

Write-Output '==== 9/9 命名空间与引用补齐 ===='
# 复制阶段只替换带尾随反斜杠的引用，文件头部 `namespace App\Models;` 这类声明会漏，
# 以及 view()/route()/asset() 里的隐式引用需要补 shop 前缀 —— 这两个脚本负责收尾。
& php (Join-Path $Root 'tools\fix-shop-namespace.php')
& php (Join-Path $Root 'tools\fix-shop-refs.php')

Write-Output '==== 报告 ===='
$report | ForEach-Object { "  $_" }
Write-Output ''
Write-Output ("模块文件数: " + (Get-ChildItem $Mod -Recurse -File | Measure-Object).Count)
