# =====================================================================
# import-erp.ps1 — 把 E:\ddwl-erp\erp 搬进 App\Modules\Erp
# 与 import-pm.ps1 的差异：
#   1) ERP 不做业务裁剪（只按 HC-25 丢 Console 定时任务），DropPatterns 为空
#   2) 多两组命名空间：App\Enums\ 与 App\Support\
#   3) ERP 路由是全平铺（无 pm. 前缀），路由文件由 rewrite-routes 单独重写
#   4) Models\User.php / BaseModel.php 由 S0 维护，复制时排除
# 可重复执行：先清空目标子目录再复制（源码只读）。
# =====================================================================
param(
    [string]$Source = 'E:\ddwl-erp\erp',
    [string]$Root   = 'E:\demo-website',
    [switch]$SkipAssets
)

$ErrorActionPreference = 'Stop'
$Mod  = Join-Path $Root 'app\Modules\Erp'
$Cur  = 'App\Modules\Erp'
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
foreach ($sub in @('Ai','Enums','Http','Policies','Services','Support','Database')) {
    $p = Join-Path $Mod $sub
    if (Test-Path -LiteralPath $p) { Remove-Item -LiteralPath $p -Recurse -Force; $report.Add("clean: $sub") }
}
$walkKeep = @('BaseModel.php','User.php')
Get-ChildItem (Join-Path $Mod 'Models') -Filter *.php -File -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -notin $walkKeep } | ForEach-Object { Remove-Item -LiteralPath $_.FullName -Force }
$viewsDir = Join-Path $Mod 'Resources\views'
if (Test-Path -LiteralPath $viewsDir) { Remove-Item -LiteralPath $viewsDir -Recurse -Force }

Write-Output '==== 2/9 复制 app 各层（不含 Console）===='
foreach ($sub in @('Ai','Enums','Http','Policies','Services','Support')) {
    $src = Join-Path $Source "app\$sub"
    $dst = Join-Path $Mod $sub
    if (-not (Test-Path -LiteralPath $src)) { $report.Add("skip-missing: app\$sub"); continue }
    New-Item -ItemType Directory -Force -Path $dst | Out-Null
    Copy-Item -Path (Join-Path $src '*') -Destination $dst -Recurse -Force
    $report.Add("copy: app\$sub")
}
# Models：ERP 的模型是子目录结构（Models/Finance/Invoice 等），递归复制；
# S0 维护的 BaseModel/User 先备份到临时目录，复制完再还原
$srcModels = Join-Path $Source 'app\Models'
$dstModels = Join-Path $Mod 'Models'
New-Item -ItemType Directory -Force -Path $dstModels | Out-Null
$tmpKeep = Join-Path $env:TEMP 'erp-model-keep'
New-Item -ItemType Directory -Force -Path $tmpKeep | Out-Null
foreach ($kf in $walkKeep) {
    $p = Join-Path $dstModels $kf
    if (Test-Path -LiteralPath $p) { Copy-Item -LiteralPath $p -Destination (Join-Path $tmpKeep $kf) -Force }
}
Copy-Item -Path (Join-Path $srcModels '*') -Destination $dstModels -Recurse -Force
foreach ($kf in $walkKeep) {
    $p = Join-Path $tmpKeep $kf
    if (Test-Path -LiteralPath $p) { Copy-Item -LiteralPath $p -Destination (Join-Path $dstModels $kf) -Force }
}
$report.Add("copy: app\Models（递归，$(Get-ChildItem $dstModels -Recurse -File -Filter *.php).Count 个）")

# ERP 的 Console 命令一律不搬（HC-25）
$srcConsole = Join-Path $Source 'app\Console'
if (Test-Path $srcConsole) {
    $n = @(Get-ChildItem $srcConsole -Recurse -File -Filter *.php).Count
    $report.Add("drop: app\Console（$n 个定时命令，HC-25）")
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
    'App\Console\'    = "$Cur\Console\"
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

Write-Output '==== 4/9 模型基类统一 ===='
$modelFiles = Get-ChildItem (Join-Path $Mod 'Models') -Filter *.php -File -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -notin @('BaseModel.php','User.php') }
$mChanged = 0
foreach ($m in $modelFiles) {
    $raw = Read-Utf8 $m.FullName
    if ($null -eq $raw) { continue }
    $new = $raw -replace 'extends\s+Model(\s|$)', 'extends BaseModel$1'
    $new = $new -replace "(?m)^use\s+Illuminate\\Database\\Eloquent\\Model;\r?\n", ''
    if ($new -ne $raw) { Write-Utf8 $m.FullName $new; $mChanged++ }
}
Write-Output "  改基类 $mChanged 个模型"

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
    $new = $new -replace "(@extends\(\s*')", '$1erp::'
    $new = $new -replace "(@include\(\s*')", '$1erp::'
    $new = $new -replace "(@includeIf\(\s*')", '$1erp::'
    $new = $new -replace "(@each\(\s*')", '$1erp::'
    $new = $new -replace "(@component\(\s*')", '$1erp::'
    $new = $new -replace "(view\(\s*')", '$1erp::'
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
    $raw = $raw -replace '(extends\s+Migration\s*\{)', "`$1`r`n    protected `$connection = 'erp';`r`n"
    Write-Utf8 (Join-Path $mg $mi.Name) $raw
    $migCount++
}
Write-Output "  迁移 $migCount 个（已注入 connection=erp）"

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
foreach ($c in @('erp.php','navigation.php','dashboard.php','activitylog.php','platforms.php','trash.php','collab.php','sanctum.php')) {
    $src = Join-Path $Source ('config\' + $c)
    if (-not (Test-Path -LiteralPath $src)) { $report.Add("skip-config: $c"); continue }
    $raw = Read-Utf8 $src
    foreach ($k in $nsMap.Keys) { $raw = $raw.Replace($k, $nsMap[$k]) }
    Write-Utf8 (Join-Path $cfgDir $c) $raw
    $report.Add("config: $c")
}
Write-Output '  已复制 ERP 专属配置到 config/'

Write-Output '==== 8/9 前端资产 ===='
if (-not $SkipAssets) {
    $pub = Join-Path $Root 'public\assets\erp'
    New-Item -ItemType Directory -Force -Path $pub | Out-Null
    foreach ($d in @('css','js','webfonts','fonts','avatars','vendor')) {
        $s = Join-Path $Source "public\$d"
        if (Test-Path -LiteralPath $s) { Copy-Item -Path $s -Destination $pub -Recurse -Force; $report.Add("asset: $d") }
    }
    Write-Output '  资产已复制到 public/assets/erp'
}

Write-Output '==== 9/9 报告 ===='
$report | ForEach-Object { "  $_" }
Write-Output ''
Write-Output ("模块文件数: " + (Get-ChildItem $Mod -Recurse -File | Measure-Object).Count)
