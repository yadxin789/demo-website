# ============================================================================
#  check-http.ps1 —— 演示站全链路 HTTP 验收（S4-18 / S7-10 扩到五产品 + 门户）
#
#  用法： pwsh tools\check-http.ps1           自动起 serve(8160) → 验收 → 停
#          pwsh tools\check-http.ps1 -Port 8190
#
#  验收项：
#    1) 门户首页 200 且有 5 张产品卡、/about 200
#    2) 五产品登录页 200，登录成功后进入各自首页且 200
#    3) 每个产品按 route:list 自动抽样 6 条真实业务路由（已登录）全 200
#    4) guard 交叉：登录 shop 后访问 /erp/home 必须落回 /erp/login（不串号）
#    5) 五产品错误页命中各自品牌
# ============================================================================
param(
    [int]$Port = 8160,
    [string]$AppDir = 'E:\demo-website',
    [string]$Php = 'C:\tools\php83\php.exe'
)

$ErrorActionPreference = 'Stop'
$base = "http://127.0.0.1:$Port"
$pass = 0
$fail = 0
$failList = @()

function Ok([string]$t, [string]$d = '') { $script:pass++; Write-Host ("  PASS  " + $t + $(if ($d) { "  ($d)" } else { '' })) }
function No([string]$t, [string]$d = '') { $script:fail++; $script:failList += $t; Write-Host ("  FAIL  " + $t + $(if ($d) { "  ($d)" } else { '' })) }

# ---- 起服务 ----
Push-Location $AppDir
$so = 'storage\logs\chk-out.log'; $se = 'storage\logs\chk-err.log'
Remove-Item $so, $se -ErrorAction SilentlyContinue
$svc = Start-Process -FilePath $Php -ArgumentList 'artisan', 'serve', "--port=$Port" -WorkingDirectory $AppDir -RedirectStandardOutput $so -RedirectStandardError $se -PassThru -WindowStyle Hidden
Start-Sleep 3

# ERP / Shop 登录带 5 次/分钟限流，重复演练会撞桶（429 不是功能问题），先清一次。
& $Php artisan cache:clear 2>&1 | Out-Null

function Get-Token([string]$url, $session) {
    $r = Invoke-WebRequest ($base + $url) -WebSession $session -UseBasicParsing
    if ($r.Content -match 'name="_token"\s+value="([^"]+)"') { return $matches[1] }
    if ($r.Content -match "name=""_token"" value=""([^""]+)""") { return $matches[1] }
    return $null
}

function Login([string]$product, [string]$loginPath, [string]$field, [string]$account, [string]$dashPath) {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $tok = Get-Token $loginPath $s
    if (-not $tok) { No "$product 取 CSRF token"; return $null }
    $r = Invoke-WebRequest ($base + $loginPath) -Method Post -WebSession $s -UseBasicParsing -SkipHttpErrorCheck -TimeoutSec 15 `
        -Body @{ '_token' = $tok; $field = $account; 'password' = '123456' } -MaximumRedirection 5
    $final = ''
    if ($r.BaseResponse) { $final = $r.BaseResponse.RequestMessage.RequestUri.AbsolutePath }
    if ($r.StatusCode -eq 200 -and $final -like "*$dashPath*") { Ok "$product 登录 → $dashPath" } else { No "$product 登录" "code=$($r.StatusCode) final=$final"; return $null }

    $d = Invoke-WebRequest ($base + $dashPath) -WebSession $s -UseBasicParsing -SkipHttpErrorCheck -TimeoutSec 15
    if ($d.StatusCode -eq 200) { Ok "$product dashboard 200" } else { No "$product dashboard" "code=$($d.StatusCode)" }
    return $s
}

Write-Output "==== 演示站 HTTP 验收 ===="
Write-Output "[1] 门户与登录页"
$pageHome = Invoke-WebRequest ($base + '/') -UseBasicParsing -SkipHttpErrorCheck -TimeoutSec 15
if ($pageHome.StatusCode -eq 200) { Ok '门户 / 200' "len=$($pageHome.Content.Length)" } else { No '门户 /' "code=$($pageHome.StatusCode)" }
$cardCount = ([regex]::Matches($pageHome.Content, 'class="p-card"')).Count
if ($cardCount -eq 5) { Ok '门户 5 张产品卡' } else { No '门户产品卡数量' "count=$cardCount" }
$about = Invoke-WebRequest ($base + '/about') -UseBasicParsing -SkipHttpErrorCheck -TimeoutSec 15
if ($about.StatusCode -eq 200 -and $about.Content.Contains('关于')) { Ok '/about 200' } else { No '/about' "code=$($about.StatusCode)" }

$sessions = @{}
foreach ($p in @(@{n = 'erp'; lp = '/erp/login'; f = 'account'; a = 'admin'; dp = '/erp/home' },
                 @{n = 'pm'; lp = '/pm/login'; f = 'username'; a = 'admin'; dp = '/pm/dashboard' },
                 @{n = 'shop'; lp = '/shop/login'; f = 'username'; a = 'admin'; dp = '/shop/dashboard' },
                 @{n = 'mes'; lp = '/mes/login'; f = 'account'; a = 'admin'; dp = '/mes/dashboard' },
                 @{n = 'hr'; lp = '/hr/login'; f = 'account'; a = 'admin@hr.demo'; dp = '/hr/dashboard' })) {
    $lp = Invoke-WebRequest ($base + $p.lp) -UseBasicParsing -SkipHttpErrorCheck -TimeoutSec 15
    if ($lp.StatusCode -eq 200) { Ok ("{0} 登录页 200" -f $p.n) } else { No ("{0} 登录页" -f $p.n) "code=$($lp.StatusCode)" }
    $s = Login $p.n $p.lp $p.f $p.a $p.dp
    if ($s) { $sessions[$p.n] = $s }
}

Write-Output ""
Write-Output "[2] 未登录拦截（应跟随重定向落到各自登录页）"
foreach ($u in @('/erp/home', '/pm/dashboard', '/shop/dashboard', '/mes/dashboard', '/hr/dashboard')) {
    $r = Invoke-WebRequest ($base + $u) -UseBasicParsing -SkipHttpErrorCheck -TimeoutSec 15 -MaximumRedirection 5
    $final = ''
    if ($r.BaseResponse) { $final = $r.BaseResponse.RequestMessage.RequestUri.AbsolutePath }
    if ($r.StatusCode -eq 200 -and $final -like '*login*') { Ok "拦截 $u" "落点 $final" } else { No "拦截 $u" "code=$($r.StatusCode) final=$final" }
}

Write-Output ""
Write-Output "[3] 各产品业务路由抽样（已登录，按 route:list 自动取）"
$json = & $Php artisan route:list --json --except-vendor 2>$null | Out-String
$routes = $json | ConvertFrom-Json
foreach ($p in $sessions.Keys) {
    $sess = $sessions[$p]
    if ($sess -is [array]) { $sess = $sess[0] }
    $pick = $routes | Where-Object {
        ($_.method -like '*GET*') -and ($_.uri -notlike '*/{*') -and ($_.uri -like "$p/*") -and
        ($_.uri -notlike '*login*') -and ($_.uri -notlike '*logout*') -and ($_.uri -notlike '*/api/*') -and
        ($_.uri -notlike '*admin*') -and ($_.uri -notlike '*ai*') -and ($_.uri -notlike '*chat*') -and
        ($_.uri -notlike '*setting*') -and ($_.uri -notlike '*audit*') -and ($_.uri -notlike '*log*')
    } | Select-Object -First 6
    if (-not $pick) { Write-Output "  WARN  $p 抽样为空"; continue }
    foreach ($rt in $pick) {
        $u = '/' + $rt.uri
        $r = Invoke-WebRequest ($base + $u) -WebSession $sess -UseBasicParsing -SkipHttpErrorCheck -TimeoutSec 15
        if ($r.StatusCode -eq 200) { Ok ("  {0,-34} 200" -f $u) } else { No ("  {0,-34}" -f $u) "code=$($r.StatusCode)" }
    }
}

Write-Output ""
Write-Output "[4] guard 交叉（shop 会话不得进入 erp）"
if ($sessions.ContainsKey('shop')) {
    $r = Invoke-WebRequest ($base + '/erp/home') -WebSession $sessions['shop'] -UseBasicParsing -MaximumRedirection 5 -SkipHttpErrorCheck -TimeoutSec 15
    $final = ''
    if ($r.BaseResponse) { $final = $r.BaseResponse.RequestMessage.RequestUri.AbsolutePath }
    if ($final -like '*/erp/login*') { Ok 'shop 会话访问 /erp/home 被拒' "落点 $final" } else { No 'guard 交叉' "code=$($r.StatusCode) final=$final（不该通过）" }
} else { No 'guard 交叉（shop 会话缺失）' }

Write-Output ""
Write-Output "[5] 错误页品牌"
# 这一步用 curl：Invoke-WebRequest 对 404 + 大 HTML 在 artisan serve 单进程下偶发挂死
foreach ($x in @(@{u = '/erp/nope'; b = '恒创' }, @{u = '/pm/nope'; b = '智软' }, @{u = '/shop/nope'; b = '百货零售' }, @{u = '/mes/nope'; b = '精工' }, @{u = '/hr/nope'; b = '华瑞' }, @{u = '/nosuch'; b = '返回产品列表' })) {
    $f = Join-Path $env:TEMP ('http404-' + ($x.u -replace '/', '_') + '.html')
    $code = & curl.exe -s --max-time 20 -o $f -w '%{http_code}' ($base + $x.u)
    $body = ''
    if (Test-Path -LiteralPath $f) { $body = Get-Content -LiteralPath $f -Raw }
    $hit = ($code -eq '404') -and $body.Contains($x.b)
    if ($hit) { Ok ("404 " + $x.u + " 品牌=" + $x.b) } else { No ("404 " + $x.u) "code=$code 含品牌=$($body.Contains($x.b))" }
}

Write-Output ""
Write-Output ("==== 汇总：PASS {0} / FAIL {1} ====" -f $pass, $fail)
if ($fail -gt 0) { Write-Output '  失败项：'; $failList | ForEach-Object { '   - ' + $_ } }

# 连子进程一起收掉：artisan serve 会再起一个 php -S，只杀父进程会留下句柄挂住管道
Get-CimInstance Win32_Process -Filter ("ParentProcessId=" + $svc.Id) -ErrorAction SilentlyContinue |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
Stop-Process -Id $svc.Id -Force -ErrorAction SilentlyContinue
Pop-Location
if ($fail -gt 0) { exit 1 }
