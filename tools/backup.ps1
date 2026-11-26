# ============================================================================
#  backup.ps1 —— 五产品 SQLite 库 + storage 备份（Windows 本地 / 部署前快照）
#
#  用法：
#    pwsh tools\backup.ps1                 备份到 E:\demo-website\backups\YYYYMMDD-HHmmss
#    pwsh tools\backup.ps1 -Keep 7         只保留最近 7 份（默认 7）
#    pwsh tools\backup.ps1 -OutDir D:\bk    自定义输出根目录
#
#  要点：
#   - 先对每个库做 wal_checkpoint(TRUNCATE)，把 -wal 内容落盘，避免备份出脏读
#   - 库文件直接复制（SQLite 就是单文件），同时保留 -wal/-shm 若存在
#   - storage 只备份 app 侧的演示上传与日志清单，不含全部历史日志
# ============================================================================
[CmdletBinding()]
param(
    [int]$Keep = 7,
    [string]$OutDir = 'E:\demo-website\backups'
)

$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$dest = Join-Path $OutDir $stamp

Write-Output "==== SQLite 备份 ===="
Write-Output "  源  : $root\database"
Write-Output "  目标: $dest"

New-Item -ItemType Directory -Path $dest -Force | Out-Null
$dbDir = Join-Path $dest 'database'
New-Item -ItemType Directory -Path $dbDir -Force | Out-Null

$php = 'C:\tools\php83\php.exe'
$dbs = @('erp.sqlite', 'pm.sqlite', 'shop.sqlite', 'mes.sqlite', 'hr.sqlite', 'portal.sqlite')

foreach ($db in $dbs) {
    $src = Join-Path $root "database\$db"
    if (-not (Test-Path $src)) {
        Write-Output "  SKIP  $db（不存在）"
        continue
    }

    # WAL 落盘：用 PHP 打开并执行 checkpoint，失败则警告但继续（只读复制仍可用）
    $code = "try{`$p=new PDO('sqlite:' . str_replace('\\\\','/', '$($src.Replace('\','\\'))'));`$p->exec('PRAGMA wal_checkpoint(TRUNCATE)');`$p=null;echo 'ok';}catch(Throwable `$e){echo 'fail';}"
    $r = & $php -r $code 2>$null
    if ($r -ne 'ok') { Write-Output "  WARN  $db WAL checkpoint 失败，按只读方式复制" }

    Copy-Item -LiteralPath $src -Destination (Join-Path $dbDir $db) -Force
    foreach ($sfx in @('-wal', '-shm')) {
        $extra = "$src$sfx"
        if (Test-Path $extra) { Copy-Item -LiteralPath $extra -Destination (Join-Path $dbDir "$db$sfx") -Force }
    }
    $len = (Get-Item (Join-Path $dbDir $db)).Length
    Write-Output ("  OK    {0,-14} {1,10:N0} B" -f $db, $len)
}

# ---- storage：只挑有演示价值的部分 ----
$st = Join-Path $dest 'storage'
New-Item -ItemType Directory -Path $st -Force | Out-Null
foreach ($sub in @('app\public', 'logs\erp', 'logs\pm', 'logs\shop')) {
    $p = Join-Path $root "storage\$sub"
    if (Test-Path $p) {
        $t = Join-Path $st ($sub -replace '\\', '-')
        Copy-Item -LiteralPath $p -Destination $t -Recurse -Force
        Write-Output "  OK    storage\$sub"
    }
}

# ---- 元信息 ----
$meta = [ordered]@{
    backup_at   = (Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
    project     = 'demo-website'
    root        = $root
    databases   = @($dbs | Where-Object { Test-Path (Join-Path $root "database\$_") })
    git_head    = (& git -C $root rev-parse --short HEAD 2>$null)
    git_branch  = (& git -C $root rev-parse --abbrev-ref HEAD 2>$null)
}
$meta | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $dest 'backup.json') -Encoding UTF8

# ---- 轮转 ----
$all = Get-ChildItem -Path $OutDir -Directory | Sort-Object Name -Descending
if ($all.Count -gt $Keep) {
    $all | Select-Object -Skip $Keep | ForEach-Object {
        Remove-Item -LiteralPath $_.FullName -Recurse -Force
        Write-Output "  DEL   旧备份 $($_.Name)"
    }
}

$size = (Get-ChildItem $dest -Recurse -File | Measure-Object Length -Sum).Sum
Write-Output ""
Write-Output ("==== 完成：{0}（{1:N1} MB），保留最近 {2} 份 ====" -f $dest, ($size / 1MB), $Keep)
