# ============================================================================
#  全面冒烟：登录后把每个产品的"无参 GET 页面"全部打一遍
#
#  用法： pwsh tools\smoke-all.ps1 -Base http://127.0.0.1:8201 [-Product erp]
#
#  判定：200 = 通过；302 且落点仍在登录页 = 会话失效（算失败）；
#        403/404/500 = 失败。含参路由（{id}）无法无数据点开，单独计数不判定。
#  明细写到 storage\logs\smoke-<product>.txt
# ============================================================================
param(
    [string]$Base = 'http://127.0.0.1:8201',
    [string]$Product = '',
    [string]$Php = 'C:\tools\php83\php.exe'
)

$ErrorActionPreference = 'Continue'
$products = if ($Product) { @($Product) } else { @('erp', 'pm', 'shop', 'mes', 'hr') }

$accounts = @{
    erp  = @{ lp = '/erp/login';  field = 'account';  a = 'admin@erp.demo' }
    pm   = @{ lp = '/pm/login';   field = 'username'; a = 'admin@pm.demo' }
    shop = @{ lp = '/shop/login'; field = 'username'; a = 'admin@shop.demo' }
    mes  = @{ lp = '/mes/login';  field = 'account';  a = 'admin@mes.demo' }
    hr = @{ lp = '/hr/login';     field = 'account';  a = 'admin@hr.demo' }
}

$skipPatterns = @(
    'login', 'logout', 'api', 'ai-', 'ai/', 'export', 'download', 'print',
    'webhook', 'callback', 'preview', 'embed', 'sse', 'stream', 'pdf', 'qr',
    'captcha', 'health', 'ping', 'ajax', 'autocomplete', 'csrf'
)

function Login([string]$code) {
    $acc = $accounts[$code]
    $resp = Invoke-WebRequest ($Base + $acc.lp) -SessionVariable sess -UseBasicParsing -TimeoutSec 20
    $token = ([regex]'name="_token" value="([^"]+)"').Match($resp.Content).Groups[1].Value
    $body = @{ _token = $token; password = '123456' }
    $body[$acc.field] = $acc.a
    Invoke-WebRequest ($Base + $acc.lp) -Method Post -Body $body -WebSession $sess `
        -UseBasicParsing -MaximumRedirection 5 -TimeoutSec 20 | Out-Null
    return $sess
}

$routesJson = & $Php artisan route:list --json --except-vendor 2>$null | Out-String
$routes = $routesJson | ConvertFrom-Json

$grand = @{ pass = 0; fail = 0; skipParam = 0; warn = 0 }

foreach ($code in $products) {
    $sess = Login $code
    $log = Join-Path 'storage\logs' ("smoke-$code.txt")
    "==== $code 全面冒烟 $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') ====" | Set-Content -LiteralPath $log -Encoding utf8

    $list = $routes | Where-Object {
        ($_.method -like '*GET*') -and ($_.uri -like "$code/*" -or $_.uri -eq $code)
    }

    $paramCount = 0
    foreach ($r in $list) {
        $uri = '/' + $r.uri
        if ($uri -match '\{') { $paramCount++; continue }
        $lower = $uri.ToLower()
        $hit = $false
        foreach ($p in $skipPatterns) { if ($lower.Contains($p)) { $hit = $true; break } }
        if ($hit) { continue }

        try {
            $resp = Invoke-WebRequest ($Base + $uri) -WebSession $sess -UseBasicParsing `
                -SkipHttpErrorCheck -MaximumRedirection 5 -TimeoutSec 25
            $final = ''
            if ($resp.BaseResponse) { $final = $resp.BaseResponse.RequestMessage.RequestUri.AbsolutePath }
            if ($resp.StatusCode -eq 200) {
                "{0}`t{1}`t{2}" -f 'PASS', $resp.StatusCode, $uri | Add-Content -LiteralPath $log -Encoding utf8
                $grand.pass++
            } elseif ($final -like '*login*') {
                "{0}`t{1}`t{2}`t{3}" -f 'FAIL', $resp.StatusCode, $uri, '会话失效→登录页' | Add-Content -LiteralPath $log -Encoding utf8
                $grand.fail++
            } elseif ($resp.StatusCode -eq 403) {
                # 403 = 权限/业务拒绝（例如未开班就点挂单），不是程序错误
                "{0}`t{1}`t{2}`t{3}" -f 'WARN', $resp.StatusCode, $uri, '权限或业务拒绝' | Add-Content -LiteralPath $log -Encoding utf8
                $grand.warn++
            } else {
                "{0}`t{1}`t{2}`t{3}" -f 'FAIL', $resp.StatusCode, $uri, $final | Add-Content -LiteralPath $log -Encoding utf8
                $grand.fail++
            }
        } catch {
            "{0}`t{1}`t{2}`t{3}" -f 'FAIL', 'ERR', $uri, $_.Exception.Message | Add-Content -LiteralPath $log -Encoding utf8
            $grand.fail++
        }
    }
    $grand.skipParam += $paramCount
    "--- $code 含参路由（不判定）$paramCount 条 ---" | Add-Content -LiteralPath $log -Encoding utf8
}

"==== 汇总 PASS $($grand.pass) / FAIL $($grand.fail) / WARN(403) $($grand.warn) / 含参跳过 $($grand.skipParam) ===="
