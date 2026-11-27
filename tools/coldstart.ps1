# 冷启动演练（S7-10）
#
# 模拟服务器首次上线：把六个库整体挪走，从零建库、跑完全部迁移与种子，
# 再靠门户自检确认五套系统都能用。失败可一键回滚（-Restore）。
#
# 用法：
#   pwsh tools\coldstart.ps1            # 演练：挪走旧库 → 建空库 → 迁移 → 种子
#   pwsh tools\coldstart.ps1 -Restore   # 回滚：把挪走的旧库放回去
#   pwsh tools\coldstart.ps1 -Keep      # 演练成功后再执行：删除旧库快照目录

param(
    [switch]$Restore,
    [switch]$Keep
)

$ErrorActionPreference = 'Stop'

$root = 'E:\demo-website'
$php = 'C:\tools\php83\php.exe'
$db = Join-Path $root 'database'
$stamp = '20261127'
$snap = Join-Path $db ("_coldstart_" + $stamp)

# 六个库 + 各自的迁移路径与种子类（portal 只有迁移，没有种子）
$targets = @(
    @{ Code = 'erp';  Path = 'app/Modules/Erp/Database/Migrations';  Seeder = 'App\Modules\Erp\Database\Seeders\DatabaseSeeder' },
    @{ Code = 'pm';   Path = 'app/Modules/Pm/Database/Migrations';   Seeder = 'App\Modules\Pm\Database\Seeders\DatabaseSeeder' },
    @{ Code = 'shop'; Path = 'app/Modules/Shop/Database/Migrations'; Seeder = 'App\Modules\Shop\Database\Seeders\DatabaseSeeder' },
    @{ Code = 'mes';  Path = 'app/Modules/Mes/Database/Migrations';  Seeder = 'App\Modules\Mes\Database\Seeders\DatabaseSeeder' },
    @{ Code = 'hr';   Path = 'app/Modules/Hr/Database/Migrations';   Seeder = 'App\Modules\Hr\Database\Seeders\DatabaseSeeder' },
    @{ Code = 'portal'; Path = 'app/Core/Database/Migrations';       Seeder = $null }
)

function Say([string]$msg) { Write-Host ("[coldstart] " + $msg) -ForegroundColor Cyan }
function Ok([string]$msg) { Write-Host ("  OK    " + $msg) -ForegroundColor Green }
function Bad([string]$msg) { Write-Host ("  FAIL  " + $msg) -ForegroundColor Red }

if ($Keep) {
    if (Test-Path -LiteralPath $snap) { Remove-Item -LiteralPath $snap -Recurse -Force }
    Ok "旧库快照目录已删除：$snap"
    exit 0
}

if ($Restore) {
    if (! (Test-Path -LiteralPath $snap)) { Bad "没有快照目录：$snap"; exit 1 }
    foreach ($t in $targets) {
        $from = Join-Path $snap ($t.Code + '.sqlite')
        if (Test-Path -LiteralPath $from) {
            Copy-Item -LiteralPath $from -Destination (Join-Path $db ($t.Code + '.sqlite')) -Force
            Ok ("回滚 " + $t.Code + ".sqlite")
        }
    }
    & $php artisan config:clear | Out-Null
    & $php artisan cache:clear | Out-Null
    Say '回滚完成，请重启 dev server 后自检。'
    exit 0
}

# ---------------------------------------------------------------- 1. 快照
Say '1/4 把六个库挪到快照目录'
New-Item -ItemType Directory -Path $snap -Force | Out-Null
foreach ($t in $targets) {
    $src = Join-Path $db ($t.Code + '.sqlite')
    if (Test-Path -LiteralPath $src) {
        Move-Item -LiteralPath $src -Destination (Join-Path $snap ($t.Code + '.sqlite')) -Force
        Ok ("挪走 " + $t.Code + ".sqlite")
    } else {
        Say ("  " + $t.Code + ".sqlite 不存在，跳过")
    }
}

# ---------------------------------------------------------------- 2. 空库
Say '2/4 建六个空库文件'
foreach ($t in $targets) {
    $f = Join-Path $db ($t.Code + '.sqlite')
    if (! (Test-Path -LiteralPath $f)) { New-Item -ItemType File -Path $f -Force | Out-Null }
    Ok ("建 " + $t.Code + ".sqlite")
}

# ---------------------------------------------------------------- 3. 迁移
Say '3/4 逐库迁移'
foreach ($t in $targets) {
    $out = & $php artisan migrate --database=$($t.Code) --path=$($t.Path) --force --no-interaction 2>&1
    if ($LASTEXITCODE -ne 0) { Bad ($t.Code + ' 迁移失败：' + ($out | Select-Object -Last 3)); exit 1 }
    $done = ([regex]::Matches(($out -join "`n"), 'DONE')).Count
    Ok ($t.Code + ' 迁移完成（' + $done + ' 个批次项）')
}

# ---------------------------------------------------------------- 4. 种子
Say '4/4 逐库种子'
foreach ($t in $targets) {
    if ($t.Seeder -eq $null) { Say ('  ' + $t.Code + ' 无种子，跳过'); continue }

    $out = & $php artisan db:seed --class=$($t.Seeder) --database=$($t.Code) --force --no-interaction 2>&1
    if ($LASTEXITCODE -ne 0) { Bad ($t.Code + ' 种子失败：' + ($out | Select-Object -Last 5)); exit 1 }
    Ok ($t.Code + ' 种子完成')
}

# Shop 的演示业务数据单独补灌（与 deploy.sh demo 同口径）
Say '额外：补灌 Shop 演示业务数据'
foreach ($cls in @('App\Modules\Shop\Database\Seeders\DemoDataSeeder', 'App\Modules\Shop\Database\Seeders\AiSeeder')) {
    $out = & $php artisan db:seed --class=$cls --database=shop --force --no-interaction 2>&1
    if ($LASTEXITCODE -ne 0) { Bad ('Shop 业务数据失败：' + ($out | Select-Object -Last 3)); exit 1 }
}
Ok 'Shop 演示业务数据完成'

# 冷启动会重建权限表：spatie 的权限缓存里还留着旧库快照，不清会让权限页全 403
foreach ($c in @('permission:cache-reset', 'cache:clear')) {
    & $php artisan $c 2>&1 | Out-Null
}
Ok '权限缓存与框架缓存已重置'

Say '冷启动完成。下一步：重启 dev server，再跑 check-walkthrough / check-cross-login / persona-check。'
Say ('失败要回滚：pwsh tools\coldstart.ps1 -Restore（快照目录 ' + $snap + '）')
