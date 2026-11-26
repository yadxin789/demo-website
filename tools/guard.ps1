# ============================================================================
#  demo-website 门禁脚本  G-01 ~ G-17
#  用法： pwsh tools\guard.ps1            （全量）
#         pwsh tools\guard.ps1 -SkipLint  （跳过 php -l，快速自查）
#  退出码： 0 = 无 FAIL；1 = 有 FAIL
#  设计原则：只读，不修改任何文件
# ============================================================================
param(
    [switch]$SkipLint,
    [switch]$SkipAutoload
)

$ErrorActionPreference = 'Continue'
$script:Root = Split-Path -Parent $PSScriptRoot
$Products = @('erp', 'pm', 'shop', 'mes', 'hr')
$script:Results = New-Object System.Collections.Generic.List[object]
$script:Hits = @{}

function AddResult {
    param($Id, $Title, $Status, $Detail, $Hits)
    $script:Results.Add([pscustomobject]@{
            ID     = $Id
            Check  = $Title
            Status = $Status
            Detail = $Detail
            Hits   = $Hits
        })
}

function FindHits {
    param($RelPath, $Pattern, $Filter = '*.php')
    $out = New-Object System.Collections.Generic.List[string]
    $abs = Join-Path $script:Root $RelPath
    if (-not (Test-Path -LiteralPath $abs)) { return $out }
    $files = Get-ChildItem -LiteralPath $abs -Recurse -File -Filter $Filter -ErrorAction SilentlyContinue |
        Where-Object { $_.FullName -notmatch '\\vendor\\|\\node_modules\\|\\storage\\|\\\.git\\|\\public\\build\\' }
    foreach ($f in $files) {
        $m = Select-String -LiteralPath $f.FullName -Pattern $Pattern -AllMatches -ErrorAction SilentlyContinue
        if ($m) {
            foreach ($hit in $m) {
                $rel = $f.FullName.Replace($script:Root + '\', '')
                $out.Add(("{0}:{1}" -f $rel, $hit.LineNumber))
            }
        }
    }
    return $out
}

function FindHitsInText {
    param($RelPath, $Pattern, $Filter = '*.php', $TextCheck)
    $out = New-Object System.Collections.Generic.List[string]
    $abs = Join-Path $script:Root $RelPath
    if (-not (Test-Path -LiteralPath $abs)) { return $out }
    $files = Get-ChildItem -LiteralPath $abs -Recurse -File -Filter $Filter -ErrorAction SilentlyContinue
    foreach ($f in $files) {
        $lines = Get-Content -LiteralPath $f.FullName -ErrorAction SilentlyContinue
        $n = 0
        foreach ($line in $lines) {
            $n++
            if ($line -match $Pattern) {
                if ($null -eq $TextCheck -or (& $TextCheck $line)) {
                    $rel = $f.FullName.Replace($script:Root + '\', '')
                    $out.Add(("{0}:{1}" -f $rel, $n))
                }
            }
        }
    }
    return $out
}

Write-Output ''
Write-Output '================ demo-website 门禁报告 ================'
Write-Output ("项目根 : " + $script:Root)
Write-Output ("时间   : " + (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'))
Write-Output ''

# ---------------------------------------------------------------------------
# G-01 零跨产品代码依赖
# ---------------------------------------------------------------------------
$g01Fails = New-Object System.Collections.Generic.List[string]
foreach ($p in $Products) {
    $others = $Products | Where-Object { $_ -ne $p }
    $pat = 'App\\Modules\\(' + ($others -join '|') + ')\\'
    $hits = FindHits ("app\Modules\$p") $pat
    foreach ($h in $hits) { $g01Fails.Add("[$p] $h") }
}
if ($g01Fails.Count -gt 0) {
    AddResult 'G-01' '零跨产品代码依赖' 'FAIL' ($g01Fails.Count.ToString() + ' 处引用其他产品命名空间') -Hits $g01Fails
} else { AddResult 'G-01' '零跨产品代码依赖' 'PASS' '无跨产品命名空间引用' }

# ---------------------------------------------------------------------------
# G-02 模型必须 extends BaseModel
# ---------------------------------------------------------------------------
$g02 = FindHits 'app\Modules' 'extends\s+Model\b' | Where-Object { $_ -notmatch 'BaseModel' }
if ($g02.Count -gt 0) {
    AddResult 'G-02' '模型必须 extends BaseModel' 'FAIL' ($g02.Count.ToString() + ' 处裸 extends Model') -Hits $g02
} else { AddResult 'G-02' '模型必须 extends BaseModel' 'PASS' '无裸 extends Model' }

# ---------------------------------------------------------------------------
# G-03 视图必须带产品命名空间（{x}::）
# ---------------------------------------------------------------------------
$g03 = FindHitsInText 'app\Modules' "view\(\s*'" '*.php' { param($line) $line -notmatch "view\(\s*'[^']*::" }
if ($g03.Count -gt 0) {
    AddResult 'G-03' '视图必须带 {x}:: 前缀' 'FAIL' ($g03.Count.ToString() + ' 处裸 view()') -Hits $g03
} else { AddResult 'G-03' '视图必须带 {x}:: 前缀' 'PASS' '无裸 view()' }

# ---------------------------------------------------------------------------
# G-04 禁止 View::share()
# ---------------------------------------------------------------------------
$g04 = New-Object System.Collections.Generic.List[string]
foreach ($d in @('app', 'resources', 'config', 'routes', 'bootstrap')) {
    foreach ($h in (FindHits $d 'View::share|view\(\)->share')) { $g04.Add($h) }
}
if ($g04.Count -gt 0) {
    AddResult 'G-04' '禁止 View::share()' 'FAIL' ($g04.Count.ToString() + ' 处') -Hits $g04
} else { AddResult 'G-04' '禁止 View::share()' 'PASS' '无全局视图共享' }

# ---------------------------------------------------------------------------
# G-05 禁止跨库连接（模型/查询显式指定他产品连接）
# ---------------------------------------------------------------------------
$g05 = New-Object System.Collections.Generic.List[string]
foreach ($p in $Products) {
    $others = $Products | Where-Object { $_ -ne $p }
    $hits = FindHitsInText ("app\Modules\$p") "connection\(\s*'([a-z]+)'" '*.php' {
        param($line)
        if ($line -match "connection\(\s*'([a-z]+)'") {
            $name = $Matches[1]
            return (($Products -contains $name) -and ($name -ne $p))
        }
        return $false
    }
    foreach ($h in $hits) { $g05.Add("[$p] $h") }
}
if ($g05.Count -gt 0) {
    AddResult 'G-05' '禁止跨库连接' 'FAIL' ($g05.Count.ToString() + ' 处指向他产品连接') -Hits $g05
} else { AddResult 'G-05' '禁止跨库连接' 'PASS' '无跨库连接' }

# ---------------------------------------------------------------------------
# G-06 路由名必须带产品前缀
# ---------------------------------------------------------------------------
$g06 = New-Object System.Collections.Generic.List[string]
# 以运行时最终路由名为准：源码里 group 内的相对名（如 name('hr.positions')）会被外层
# name('erp.') 拼成 erp.hr.positions，文本扫描会把它误判成"误用其它产品前缀"。
$routeRows = & php artisan route:list --json --except-vendor 2>$null | ConvertFrom-Json
if (-not $routeRows) {
    AddResult 'G-06' '路由名必须带产品前缀' 'FAIL' 'route:list 无法解析' -Hits @('php artisan route:list --json 返回空')
} else {
    foreach ($r in $routeRows) {
        if (-not $r.name) { continue }
        # 框架基础设施路由不归属任何产品，必须放行：
        #   storage.local / storage.local.upload —— Storage::disk('local')->serve(true) 注册的文件流
        #   generated::* —— 框架内部生成名
        #   路由名看不出归属但 uri 恰为 up —— Laravel 12 内置健康检查 /up
        if ($r.name -match '^storage\.local(\.|$)') { continue }
        if ($r.name -match '^generated::') { continue }
        if ($r.uri -eq 'up') { continue }
        $owner = ($r.name -split '\.')[0]
        if ($owner -eq 'portal') { continue }
        if ($Products -notcontains $owner) {
            $g06.Add("路由名 '" + $r.name + "' 无产品前缀（uri " + $r.uri + "）")
            continue
        }
        $seg = ($r.uri -split '/')[0]
        if (($Products -contains $seg) -and ($seg -ne $owner)) {
            $g06.Add("路由名 '" + $r.name + "' 与 URI '/" + $r.uri + "' 产品不一致")
        }
    }
}
if ($g06.Count -gt 0) {
    AddResult 'G-06' '路由名必须带产品前缀' 'FAIL' ($g06.Count.ToString() + ' 处前缀错误') -Hits $g06
} else { AddResult 'G-06' '路由名必须带产品前缀' 'PASS' ('全部合规，共 ' + @($routeRows).Count + ' 条') }

# ---------------------------------------------------------------------------
# G-07 路由条数差集（需基线快照）
# ---------------------------------------------------------------------------
$baselineFile = Join-Path $script:Root 'tools\baseline\routes.json'
if (-not (Test-Path -LiteralPath $baselineFile)) {
    AddResult 'G-07' '路由条数差集' 'SKIP' '无基线快照（先跑 tools\snapshot-routes.ps1）'
} else {
    $base = Get-Content -LiteralPath $baselineFile -Raw | ConvertFrom-Json
    Push-Location $script:Root
    $jsonRoutes = & php artisan route:list --json --except-vendor 2>$null | ConvertFrom-Json
    Pop-Location
    $segCount = @{}
    foreach ($r in $jsonRoutes) {
        $seg = ([string]$r.uri -split '/')[0]
        if ($seg -eq '') { $seg = '(root)' }
        if (-not $segCount.ContainsKey($seg)) { $segCount[$seg] = 0 }
        $segCount[$seg]++
    }
    $g07 = New-Object System.Collections.Generic.List[string]
    foreach ($code in $Products) {
        $b = $base.products.$code
        if ($null -eq $b) { continue }
        if (-not $b.migrated) { continue }
        $act = 0
        if ($segCount.ContainsKey($code)) { $act = $segCount[$code] }
        $min = [int][Math]::Floor([double]$b.expected * 0.7)
        if ($act -lt $min) { $g07.Add("$code 实际 $act 条 < 基线下限 $min（源 expected=$($b.expected)）") }
    }
    if ($g07.Count -gt 0) {
        AddResult 'G-07' '路由条数差集' 'FAIL' ($g07.Count.ToString() + ' 个产品路由数量低于基线') -Hits $g07
    } else {
        AddResult 'G-07' '路由条数差集' 'PASS' ('基线比对通过，共 ' + $jsonRoutes.Count.ToString() + ' 条路由')
    }
}

# ---------------------------------------------------------------------------
# G-08 迁移必须显式声明 $connection（HC-15 / HC-17）
# 五库独立后，同名表在不同产品库里是允许的（users / cache / migrations 每库各一份，HC-18），
# 所以这里不再比"表名唯一"，只守真正会出事的那条：本产品的迁移必须落在本产品的库。
# ---------------------------------------------------------------------------
$g08 = New-Object System.Collections.Generic.List[string]
$migTotal = 0
$dupTables = New-Object System.Collections.Generic.List[string]
$tableOwners = @{}
foreach ($p in $Products) {
    $migDir = Join-Path $script:Root "app\Modules\$p\Database\Migrations"
    if (-not (Test-Path -LiteralPath $migDir)) { continue }
    foreach ($f in (Get-ChildItem -LiteralPath $migDir -File -Filter '*.php' -ErrorAction SilentlyContinue)) {
        $migTotal++
        $text = Get-Content -LiteralPath $f.FullName -Raw -ErrorAction SilentlyContinue
        if ($text -notmatch ("connection\s*=\s*'" + $p + "'")) {
            $g08.Add("[$p] " + $f.Name + " 未声明 connection=$p")
        }
        foreach ($mm in [regex]::Matches($text, "Schema::create\(\s*'([^']+)'")) {
            $tbl = $mm.Groups[1].Value
            if (-not $tableOwners.ContainsKey($tbl)) { $tableOwners[$tbl] = @() }
            if ($tableOwners[$tbl] -notcontains $p) { $tableOwners[$tbl] += $p }
        }
    }
}
if ($g08.Count -gt 0) {
    AddResult 'G-08' '迁移连接必须显式' 'FAIL' ($g08.Count.ToString() + ' 个迁移缺 connection 声明') -Hits $g08
} else {
    AddResult 'G-08' '迁移连接必须显式' 'PASS' ($migTotal.ToString() + ' 个迁移全部声明 connection')
}

# ---------------------------------------------------------------------------
# G-09 禁止 CDN
# ---------------------------------------------------------------------------
$g09 = New-Object System.Collections.Generic.List[string]
foreach ($d in @('resources\views', 'app\Modules')) {
    $hits = FindHits $d 'cdn\.jsdelivr|unpkg\.com|cdnjs\.cloudflare|cdn\.bootcss|bootcdn|fonts\.googleapis|cdn\.bootcdn' '*.php'
    foreach ($h in $hits) { $g09.Add($h) }
}
if ($g09.Count -gt 0) {
    AddResult 'G-09' '禁止 CDN 引用' 'FAIL' ($g09.Count.ToString() + ' 处外链') -Hits $g09
} else { AddResult 'G-09' '禁止 CDN 引用' 'PASS' '全部本地资产' }

# ---------------------------------------------------------------------------
# G-10 演示密码只许出现在 Seeders
# ---------------------------------------------------------------------------
$g10 = FindHitsInText 'app' "'123456'" '*.php' {
    param($line)
    return ($line -notmatch '\\\\\\\\|Seeder' )
}
# 二次过滤：路径必须包含 Seeder 目录；blade 视图里的"演示密码展示"不算违规
$g10 = $g10 | Where-Object { $_ -notmatch 'Seeders\\' -and $_ -notmatch 'Resources\\views\\' }
if ($g10.Count -gt 0) {
    AddResult 'G-10' '密码 123456 只在 Seeder 出现' 'FAIL' ($g10.Count.ToString() + ' 处非 Seeder 硬编码') -Hits $g10
} else { AddResult 'G-10' '密码 123456 只在 Seeder 出现' 'PASS' '无越界硬编码' }

# ---------------------------------------------------------------------------
# G-11 禁止定时任务
# ---------------------------------------------------------------------------
$g11 = New-Object System.Collections.Generic.List[string]
$hits = FindHits 'app' 'Schedule::|->withSchedule\(|->command\(' '*.php'
foreach ($h in $hits) {
    # ApsSchedule / aps_schedules 是 MES 的"排产结果"表，不是定时任务，必须放行
    if ($h -match '^(.*):(\d+)$') {
        $relPath = $matches[1]
        $lineNo = [int]$matches[2]
        $absPath = Join-Path $script:Root $relPath
        $text = (Get-Content -LiteralPath $absPath -ErrorAction SilentlyContinue | Select-Object -Skip ($lineNo - 1) -First 1)
        if ("$text" -match 'ApsSchedule|aps_schedule') { continue }
    }
    $g11.Add($h)
}
if (Test-Path (Join-Path $script:Root 'routes\console.php')) {
    $console = Join-Path $script:Root 'routes\console.php'
    foreach ($l in (Get-Content -LiteralPath $console -ErrorAction SilentlyContinue)) {
        if ($l -match 'Schedule::') { $g11.Add('routes/console.php: schedule') }
    }
}
if ($g11.Count -gt 0) {
    AddResult 'G-11' '禁止定时任务' 'FAIL' ($g11.Count.ToString() + ' 处') -Hits $g11
} else { AddResult 'G-11' '禁止定时任务' 'PASS' '无调度代码' }

# ---------------------------------------------------------------------------
# G-12 collab / voice 残留
# ---------------------------------------------------------------------------
$g12 = New-Object System.Collections.Generic.List[string]
# 只查 PM 自己的目录：ERP 原生带 OnlyOffice 协同（rnd collab），不在 PM 裁剪范围（HC-26）
foreach ($h in (FindHits 'app\Modules\Pm' 'CollabDocument|VoiceRoom|VoiceSession|VoiceParticipant|CollabOfficeService|VoiceTokenService|WecomCallback')) { $g12.Add($h) }
foreach ($d in @('public\assets\pm\css', 'public\assets\pm\js')) {
    if (Test-Path -LiteralPath (Join-Path $script:Root $d)) {
        foreach ($h in (FindHits $d 'CollabDocument|VoiceRoom|CollabOfficeService|voice_?room')) { $g12.Add($h) }
    }
}
$g12 = $g12 | Where-Object { $_ -notmatch 'tools\\guard' }
if ($g12.Count -gt 0) {
    AddResult 'G-12' 'PM 协同办公/电话会议已清除' 'FAIL' ($g12.Count.ToString() + ' 处残留') -Hits $g12
} else { AddResult 'G-12' 'PM 协同办公/电话会议已清除' 'PASS' '无残留' }

# ---------------------------------------------------------------------------
# G-13 php -l 语法检查
# ---------------------------------------------------------------------------
if ($SkipLint) {
    AddResult 'G-13' '全量 php -l' 'SKIP' '-SkipLint'
} else {
    $g13 = New-Object System.Collections.Generic.List[string]
    $files = Get-ChildItem -LiteralPath (Join-Path $script:Root 'app') -Recurse -File -Filter '*.php' -ErrorAction SilentlyContinue
    foreach ($f in $files) {
        $r = & php -l $f.FullName 2>&1
        if ($LASTEXITCODE -ne 0) { $g13.Add($f.FullName.Replace($script:Root + '\', '') + ' -> ' + ($r -join ' ')) }
    }
    if ($g13.Count -gt 0) {
        AddResult 'G-13' '全量 php -l' 'FAIL' ($g13.Count.ToString() + ' 个文件语法错误') -Hits $g13
    } else { AddResult 'G-13' '全量 php -l' 'PASS' ($files.Count.ToString() + ' 个文件通过') }
}

# ---------------------------------------------------------------------------
# G-14 autoload / 类可发现
# ---------------------------------------------------------------------------
if ($SkipAutoload) {
    AddResult 'G-14' 'composer dump-autoload' 'SKIP' '-SkipAutoload'
} else {
    Push-Location $script:Root
    $out = & composer dump-autoload -o --no-scripts 2>&1
    $code = $LASTEXITCODE
    Pop-Location
    if ($code -ne 0) {
        AddResult 'G-14' 'composer dump-autoload' 'FAIL' (($out | Select-Object -Last 3) -join ' / ')
    } else { AddResult 'G-14' 'composer dump-autoload' 'PASS' 'PSR-4 映射正常' }
}

# ---------------------------------------------------------------------------
# G-15 产品资产齐备
# ---------------------------------------------------------------------------
$g15 = New-Object System.Collections.Generic.List[string]
foreach ($p in $Products) {
    $css = Join-Path $script:Root "public\assets\$p\$p.css"
    if (-not (Test-Path -LiteralPath $css)) { $g15.Add("缺 public/assets/$p/$p.css") }
}
if ($g15.Count -gt 0) {
    AddResult 'G-15' '五产品资产齐备' 'WARN' ($g15.Count.ToString() + ' 项待补（S0/S5/S6 补齐）') -Hits $g15
} else { AddResult 'G-15' '五产品资产齐备' 'PASS' '五套主题 css 均在位' }

# ---------------------------------------------------------------------------
# G-16 预留位白名单（Mes/Hr 开发前不得有实现代码）
# ---------------------------------------------------------------------------
$g16 = New-Object System.Collections.Generic.List[string]
foreach ($p in @('mes', 'hr')) {
    $base = Join-Path $script:Root "app\Modules\$($p.Substring(0,1).ToUpper())$($p.Substring(1))"
    if (-not (Test-Path -LiteralPath $base)) { continue }

    # 只约束"尚未开发"的预留位：config/products.php 里 enabled=true 的产品已经是正式模块，
    # 它的实现代码不应再被当成越界（否则每完成一个产品都要改一次门禁）
    $productsRaw = Get-Content -LiteralPath (Join-Path $script:Root 'config\products.php') -Raw
    $segMatch = [regex]::Match($productsRaw, "(?s)'" + $p + "'\s*=>\s*\[(.*?)\n        \],")
    if ($segMatch.Success -and ($segMatch.Groups[1].Value -match "enabled'\s*=>\s*true")) {
        continue
    }

    foreach ($f in (Get-ChildItem -LiteralPath $base -Recurse -File -Filter '*.php' -ErrorAction SilentlyContinue)) {
        $rel = $f.FullName.Replace($base + '\', '')
        $allowed = ($rel -match '^Models\\BaseModel\.php$') -or
                   ($rel -match '^Models\\User\.php$') -or
                   ($rel -match '^Providers\\') -or
                   ($rel -match '^Routes\\web\.php$') -or
                   ($rel -match '^Database\\Migrations\\')
        if (-not $allowed) { $g16.Add("$p/$rel") }
    }
}
if ($g16.Count -gt 0) {
    AddResult 'G-16' '预留位白名单' 'FAIL' ($g16.Count.ToString() + ' 个越界文件') -Hits $g16
} else { AddResult 'G-16' '预留位白名单' 'PASS' 'Mes/Hr 无越界实现' }

# ---------------------------------------------------------------------------
# G-17 enabled 与入口一致性
# ---------------------------------------------------------------------------
$cfg = Join-Path $script:Root 'config\products.php'
if (-not (Test-Path -LiteralPath $cfg)) {
    AddResult 'G-17' 'enabled 一致性' 'SKIP' 'config/products.php 尚未创建（S0-01）'
} else {
    $g17 = New-Object System.Collections.Generic.List[string]
    Push-Location $script:Root
    $json = & php -r "`$c = require 'config/products.php'; `$items = `$c['products'] ?? []; `$o = []; foreach(`$items as `$k=>`$v){ `$o[] = ['code'=>`$v['code'] ?? `$k, 'enabled'=> (bool)(`$v['enabled'] ?? true)]; } echo json_encode(`$o);" 2>&1
    Pop-Location
    $parsed = $null
    try { $parsed = $json | ConvertFrom-Json } catch { }
    if ($null -eq $parsed) {
        AddResult 'G-17' 'enabled 一致性' 'WARN' '配置解析失败'
    } else {
        foreach ($item in $parsed) {
            $code = $item.code
            $routesFile = Join-Path $script:Root ("app\Modules\" + $code.Substring(0,1).ToUpper() + $code.Substring(1) + "\Routes\web.php")
            $hasRoutes = $false
            if (Test-Path -LiteralPath $routesFile) {
                $txt = Get-Content -LiteralPath $routesFile -Raw
                $hasRoutes = ($txt -match 'Route::')
            }
            if ($item.enabled -and -not $hasRoutes) { $g17.Add("$code enabled=true 但无路由") }
            if ((-not $item.enabled) -and $hasRoutes) { $g17.Add("$code enabled=false 但有路由") }
        }
        if ($g17.Count -gt 0) {
            AddResult 'G-17' 'enabled 一致性' 'FAIL' ($g17.Count.ToString() + ' 处不一致') -Hits $g17
        } else { AddResult 'G-17' 'enabled 一致性' 'PASS' '五产品开关与路由一致' }
    }
}

# ---------------------------------------------------------------------------
# 输出
# ---------------------------------------------------------------------------
$script:Results | Format-Table -AutoSize -Wrap ID, Check, Status, Detail
$failCount = ($script:Results | Where-Object { $_.Status -eq 'FAIL' }).Count
$warnCount = ($script:Results | Where-Object { $_.Status -eq 'WARN' }).Count
$skipCount = ($script:Results | Where-Object { $_.Status -eq 'SKIP' }).Count

foreach ($r in $script:Results) {
    if ($null -eq $r.Hits) { continue }
    $list = @($r.Hits)
    if ($list.Count -eq 0) { continue }
    Write-Output ''
    Write-Output ("[" + $r.ID + "] 违规清单：")
    foreach ($h in ($list | Select-Object -First 30)) { Write-Output ('  - ' + $h) }
    if ($list.Count -gt 30) { Write-Output ('  ... 共 ' + $list.Count + ' 条') }
}

Write-Output ''
Write-Output ("汇总： PASS " + ($script:Results | Where-Object { $_.Status -eq 'PASS' }).Count + " / FAIL " + $failCount + " / WARN " + $warnCount + " / SKIP " + $skipCount)
Write-Output '======================================================'
Write-Output ''
if ($failCount -gt 0) { exit 1 } else { exit 0 }
