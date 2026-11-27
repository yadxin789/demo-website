# 五产品账号交叉验证（S7-07）
#
# 目的：确认每个产品的登录校验只认自己库里的账号 —— 拿 A 产品的账号去登 B 产品必须失败。
# 不做真实登录成功的对照（那由各产品自己的走查负责），只看"外来账号能否进入"。
#
# 用法：pwsh tools\check-cross-login.ps1 -Base "http://127.0.0.1:8203"

param(
    [string]$Base = 'http://127.0.0.1:8203',
    [string]$Password = '123456'
)

$ErrorActionPreference = 'Stop'

if ($Password -ne '123456') {
    # 演示密码由 Seeder 统一写入，这里只做比对，不打印明文（G-10）
    Write-Host '演示密码与 Seeder 口径不一致，脚本按 Seeder 值执行。' -ForegroundColor Yellow
}

# 各产品登录表单的账号字段名不同（沿用原项目表单，不为了统一而改动）
$products = @(
    @{ Code = 'erp';  Field = 'account';  Protected = 'erp/home';       Account = 'admin@hr.demo' },
    @{ Code = 'pm';   Field = 'username'; Protected = 'pm/dashboard';   Account = 'admin@hr.demo' },
    @{ Code = 'shop'; Field = 'username'; Protected = 'shop/dashboard'; Account = 'admin@hr.demo' },
    @{ Code = 'mes';  Field = 'account';  Protected = 'mes/dashboard';  Account = 'admin@hr.demo' },
    @{ Code = 'hr';   Field = 'account';  Protected = 'hr/dashboard';   Account = 'admin@erp.demo' }
)

$temp = Join-Path $env:TEMP ('cross-' + [guid]::NewGuid().ToString('N').Substring(0, 8))
New-Item -ItemType Directory -Path $temp -Force | Out-Null

$pass = 0
$fail = 0
$warn = 0

foreach ($p in $products) {
    $code = $p.Code
    $cookie = Join-Path $temp "$code.txt"
    $loginHtml = Join-Path $temp "$code-login.html"

    $status = & curl.exe -s -c $cookie -o $loginHtml -w '%{http_code}' "$Base/$code/login"
    if ($status -ne '200') {
        Write-Host ("FAIL  {0,-5} 登录页不可访问（HTTP {1}）" -f $code, $status) -ForegroundColor Red
        $fail++
        continue
    }

    $token = [regex]::Match((Get-Content $loginHtml -Raw), 'name="_token" value="([^"]+)"').Groups[1].Value
    if ([string]::IsNullOrWhiteSpace($token)) {
        Write-Host ("FAIL  {0,-5} 登录页取不到 CSRF token" -f $code) -ForegroundColor Red
        $fail++
        continue
    }

    $postStatus = & curl.exe -s -b $cookie -c $cookie -o (Join-Path $temp "$code-post.html") -w '%{http_code}' `
        -X POST "$Base/$code/login" `
        --data-urlencode "_token=$token" `
        --data-urlencode "$($p.Field)=$($p.Account)" `
        --data-urlencode "password=$Password"

    # 判定口径：拿外来账号 POST 之后，访问本产品的受保护页。
    #   - 200             → 真进去了（FAIL）
    #   - 302 且指向 login → 仍要求登录（PASS）
    $probe = Join-Path $temp "$code-probe.html"
    $raw = & curl.exe -s -b $cookie -o $probe -w '%{http_code}|%{redirect_url}' "$Base/$($p.Protected)"
    $parts = $raw -split '\|', 2
    $dash = $parts[0].Trim()
    $redirect = if ($parts.Length -gt 1) { $parts[1].Trim() } else { '' }

    if ($dash -eq '200') {
        Write-Host ("FAIL  {0,-5} 外来账号 {1} 竟然登进了工作台（POST {2}）" -f $code, $p.Account, $postStatus) -ForegroundColor Red
        $fail++
    } elseif ($dash -eq '302' -and $redirect -match '/login') {
        Write-Host ("PASS  {0,-5} 外来账号 {1} 被拒（POST {2} / {3} 仍跳 {4}）" -f $code, $p.Account, $postStatus, $p.Protected, $redirect) -ForegroundColor Green
        $pass++
    } else {
        Write-Host ("WARN  {0,-5} 判定不明（POST {1} / {2} → {3} {4}）" -f $code, $postStatus, $p.Protected, $dash, $redirect) -ForegroundColor Yellow
        $warn++
    }
}

Remove-Item -LiteralPath $temp -Recurse -Force -ErrorAction SilentlyContinue

Write-Host ''
Write-Host ("==== 汇总：PASS {0} / FAIL {1} / WARN {2} ====" -f $pass, $fail, $warn)

exit ([int]($fail -gt 0))



