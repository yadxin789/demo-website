# =====================================================================
# import-pm.ps1 — 把 E:\my-pm\pm-server-openEuler 搬进 App\Modules\Pm
# 只做"搬运 + 规则化改写"，不做业务逻辑修改。
# 可重复执行：每次先清空目标子目录再复制（源码目录只读，不动）。
# =====================================================================
param(
    [string]$Source = 'E:\my-pm\pm-server-openEuler',
    [string]$Root   = 'E:\demo-website',
    [switch]$SkipAssets
)

$ErrorActionPreference = 'Stop'
$Mod  = Join-Path $Root 'app\Modules\Pm'
$Cur  = 'App\Modules\Pm'
$enc  = New-Object System.Text.UTF8Encoding($false)   # PHP/blade 一律 UTF-8 无 BOM
$report = New-Object System.Collections.Generic.List[string]

function Write-Utf8([string]$Path, [string]$Text) {
    $dir = Split-Path -Parent $Path
    if (-not (Test-Path -LiteralPath $dir)) { New-Item -ItemType Directory -Force -Path $dir | Out-Null }
    [System.IO.File]::WriteAllText($Path, $Text, $enc)
}
function Read-Utf8([string]$Path) {
    return [System.IO.File]::ReadAllText($Path, [System.Text.Encoding]::UTF8)
}

# 需要整体丢弃的文件/目录（协同办公 / 电话会议 / 企业微信）
$DropPatterns = @(
    'CollabDocument*', 'Voice*', 'Wecom*', 'UserWecom*'
)

Write-Output '==== 1/8 清理目标子目录 ===='
$keepModels = @('BaseModel.php', 'User.php')      # 这两个由我们维护，稍后单独处理
foreach ($sub in @('Ai','Helpers','Http','Jobs','Notifications','Policies','Services','States','Support','Traits','Database')) {
    $p = Join-Path $Mod $sub
    if (Test-Path -LiteralPath $p) { Remove-Item -LiteralPath $p -Recurse -Force; $report.Add("clean: $sub") }
}
Get-ChildItem (Join-Path $Mod 'Models') -Filter *.php -File -ErrorAction SilentlyContinue |
    Where-Object { $_.Name -notin $keepModels } | ForEach-Object { Remove-Item -LiteralPath $_.FullName -Force }
$viewsDir = Join-Path $Mod 'Resources\views'
if (Test-Path -LiteralPath $viewsDir) { Remove-Item -LiteralPath $viewsDir -Recurse -Force }

Write-Output '==== 2/8 复制 app 各层 ===='
foreach ($sub in @('Ai','Helpers','Http','Jobs','Models','Notifications','Policies','Services','States','Support','Traits')) {
    $src = Join-Path $Source "app\$sub"
    $dst = Join-Path $Mod $sub
    if (-not (Test-Path -LiteralPath $src)) { $report.Add("skip-missing: app\$sub"); continue }
    New-Item -ItemType Directory -Force -Path $dst | Out-Null
    Copy-Item -Path (Join-Path $src '*') -Destination $dst -Recurse -Force
    $report.Add("copy: app\$sub")
}

Write-Output '==== 3/8 删除裁剪目标 ===='
$dropped = 0
foreach ($pat in $DropPatterns) {
    Get-ChildItem $Mod -Recurse -File -Filter *.php -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -like $pat } | ForEach-Object {
            Remove-Item -LiteralPath $_.FullName -Force
            $dropped++
            $report.Add("drop: " + $_.FullName.Replace($Mod, ''))
        }
}
# PM 自定义 Role 与 spatie 默认冲突：改用 Spatie 标准模型（permission 配置全局一份，不能指向模块类）
$roleFile = Join-Path $Mod 'Models\Role.php'
if (Test-Path -LiteralPath $roleFile) { Remove-Item -LiteralPath $roleFile -Force; $report.Add('drop: Models\Role.php（改用 spatie 标准 Role）') }
Write-Output "  删除 $dropped 个文件"

Write-Output '==== 4/8 命名空间改写 ===='
$nsMap = [ordered]@{
    'App\Http\'           = "$Cur\Http\"
    'App\Models\'         = "$Cur\Models\"
    'App\Services\'       = "$Cur\Services\"
    'App\Notifications\'  = "$Cur\Notifications\"
    'App\Policies\'       = "$Cur\Policies\"
    'App\States\'         = "$Cur\States\"
    'App\Support\'        = "$Cur\Support\"
    'App\Traits\'         = "$Cur\Traits\"
    'App\Jobs\'           = "$Cur\Jobs\"
    'App\Ai\'             = "$Cur\Ai\"
    'App\Helpers\'        = "$Cur\Helpers\"
    'App\Console\'        = "$Cur\Console\"
}
$files = Get-ChildItem $Mod -Recurse -File -Include *.php,*.blade.php -ErrorAction SilentlyContinue
$changed = 0
foreach ($f in $files) {
    $raw = Read-Utf8 $f.FullName
    if ($null -eq $raw) { continue }
    $new = $raw
    foreach ($k in $nsMap.Keys) { $new = $new.Replace($k, $nsMap[$k]) }

    # 迁移文件：Laravel 迁移类在全局命名空间，不要动它的 namespace（本来就没有）

    if ($new -ne $raw) { Write-Utf8 $f.FullName $new; $changed++ }
}
Write-Output "  改写 $changed 个文件的命名空间"

Write-Output '==== 5/8 模型基类统一 ===='
# 只替换"继承 Model"的写法；User 继承 Authenticatable 不动
$models = Get-ChildItem (Join-Path $Mod 'Models') -Filter *.php -File -ErrorAction SilentlyContinue
$mChanged = 0
foreach ($m in $models) {
    $raw = Read-Utf8 $m.FullName
    if ($null -eq $raw) { continue }
    $new = $raw -replace 'extends\s+Model(\s|$)', 'extends BaseModel$1'
    $new = $new -replace "(?m)^use\s+Illuminate\\Database\\Eloquent\\Model;\r?\n", ''
    if ($new -ne $raw) { Write-Utf8 $m.FullName $new; $mChanged++ }
}
Write-Output "  改基类 $mChanged 个模型"

Write-Output '==== 6/8 视图搬迁与引用改写 ===='
$srcViews = Join-Path $Source 'resources\views'
New-Item -ItemType Directory -Force -Path $viewsDir | Out-Null
Copy-Item -Path (Join-Path $srcViews '*') -Destination $viewsDir -Recurse -Force
# 删掉协同办公/电话会议/企微视图目录
foreach ($d in @('collab','wecom')) {
    $p = Join-Path $viewsDir $d
    if (Test-Path -LiteralPath $p) { Remove-Item -LiteralPath $p -Recurse -Force; $report.Add("drop-view: $d") }
}
$vp = Join-Path $viewsDir 'project\voice.blade.php'
if (Test-Path -LiteralPath $vp) { Remove-Item -LiteralPath $vp -Force; $report.Add('drop-view: project\voice.blade.php') }

$blades = Get-ChildItem $viewsDir -Recurse -File -Filter *.blade.php
$vChanged = 0
foreach ($b in $blades) {
    $raw = Read-Utf8 $b.FullName
    if ($null -eq $raw) { continue }
    $new = $raw
    $new = $new -replace "(@extends\(\s*')", '$1pm::'
    $new = $new -replace "(@include\(\s*')", '$1pm::'
    $new = $new -replace "(@includeIf\(\s*')", '$1pm::'
    $new = $new -replace "(@each\(\s*')", '$1pm::'
    $new = $new -replace "(@component\(\s*')", '$1pm::'
    $new = $new -replace "(view\(\s*')", '$1pm::'
    if ($new -ne $raw) { Write-Utf8 $b.FullName $new; $vChanged++ }
}
Write-Output "  复制并改写 $($blades.Count) 个视图，其中 $vChanged 个需改引用"

Write-Output '==== 7/8 迁移与种子 ===='
$mg = Join-Path $Mod 'Database\Migrations'
$srcMg = Join-Path $Source 'database\migrations'
New-Item -ItemType Directory -Force -Path $mg | Out-Null
foreach ($mi in Get-ChildItem $srcMg -Filter *.php -File) {
    if ($mi.Name -match 'wecom|collab|voice') { $report.Add("drop-migration: " + $mi.Name); continue }
    $raw = Read-Utf8 $mi.FullName
    # 注入 $connection，保证迁移必然落在 pm 库（HC-15）
    $raw = $raw -replace '(return\s+new\s+class\s+extends\s+Migration\s*\{)', "`$1`r`n    protected `$connection = 'pm';`r`n"
    Write-Utf8 (Join-Path $mg $mi.Name) $raw
}
$sd = Join-Path $Mod 'Database\Seeders'
$srcSd = Join-Path $Source 'database\seeders'
if (Test-Path -LiteralPath $srcSd) {
    New-Item -ItemType Directory -Force -Path $sd | Out-Null
    Copy-Item -Path (Join-Path $srcSd '*') -Destination $sd -Recurse -Force
    foreach ($s in Get-ChildItem $sd -Recurse -File -Filter *.php) {
        $raw = Read-Utf8 $s.FullName
        $new = $raw
        foreach ($k in $nsMap.Keys) { $new = $new.Replace($k, $nsMap[$k]) }
        $new = $new.Replace('Database\Seeders', 'App\Modules\Pm\Database\Seeders')
        if ($new -ne $raw) { Write-Utf8 $s.FullName $new }
    }
}
$fa = Join-Path $Mod 'Database\Factories'
$srcFa = Join-Path $Source 'database\factories'
if (Test-Path -LiteralPath $srcFa) {
    New-Item -ItemType Directory -Force -Path $fa | Out-Null
    Copy-Item -Path (Join-Path $srcFa '*') -Destination $fa -Recurse -Force
    foreach ($s in Get-ChildItem $fa -Recurse -File -Filter *.php) {
        $raw = Read-Utf8 $s.FullName
        $new = $raw.Replace('Database\Factories', 'App\Modules\Pm\Database\Factories')
        foreach ($k in $nsMap.Keys) { $new = $new.Replace($k, $nsMap[$k]) }
        if ($new -ne $raw) { Write-Utf8 $s.FullName $new }
    }
}
Write-Output "  迁移 $((Get-ChildItem $mg -Filter *.php).Count) 个"

Write-Output '==== 8/8 前端资产 ===='
if (-not $SkipAssets) {
    $pub = Join-Path $Root 'public\assets\pm'
    New-Item -ItemType Directory -Force -Path $pub | Out-Null
    foreach ($d in @('css','js','font-awesome','webfonts','fonts','build','avatars')) {
        $s = Join-Path $Source "public\$d"
        if (Test-Path -LiteralPath $s) { Copy-Item -Path $s -Destination $pub -Recurse -Force; $report.Add("asset: $d") }
    }
    Write-Output "  资产已复制到 public/assets/pm"
}

Write-Output ''
Write-Output '==== 报告 ===='
$report | ForEach-Object { "  $_" }
Write-Output ''
Write-Output ("模块文件数: " + (Get-ChildItem $Mod -Recurse -File | Measure-Object).Count)
