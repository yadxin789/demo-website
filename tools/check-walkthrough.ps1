# 客户视角全走查（S7-09）
#
# 模拟一个潜在客户在演示站上的完整走法：
#   门户首页 → 五张卡片 → 五个产品介绍页 → 五个登录页 → 用各产品 admin 登入 → 各进一个主页面
#
# 用法：pwsh tools\check-walkthrough.ps1 -Base "http://127.0.0.1:8203"

param(
    [string]$Base = 'http://127.0.0.1:8203'
)

$ErrorActionPreference = 'Stop'

$pass = 0
$fail = 0

function Pass([string]$msg) { $script:pass++; Write-Host ("  PASS  " + $msg) -ForegroundColor Green }
function Fail([string]$msg) { $script:fail++; Write-Host ("  FAIL  " + $msg) -ForegroundColor Red }

$demoPass = '123456'

$products = @(
    @{ Code = 'erp';  Field = 'account';  Account = 'admin';         Home = 'erp/home';        Name = '电子制造 ERP'; CardWord = '供应链' },
    @{ Code = 'pm';   Field = 'username'; Account = 'admin';         Home = 'pm/dashboard';    Name = '研发项目管理'; CardWord = '项目' },
    @{ Code = 'shop'; Field = 'username'; Account = 'admin';         Home = 'shop/dashboard';  Name = '百货零售 ERP'; CardWord = 'POS' },
    @{ Code = 'mes';  Field = 'account';  Account = 'admin';         Home = 'mes/dashboard';   Name = '智能制造 MES'; CardWord = '车间' },
    @{ Code = 'hr';   Field = 'account';  Account = 'admin@hr.demo'; Home = 'hr/dashboard';    Name = '人事管理 HR'; CardWord = '考勤' }
)

$temp = Join-Path $env:TEMP ('walk-' + [guid]::NewGuid().ToString('N').Substring(0, 8))
New-Item -ItemType Directory -Path $temp -Force | Out-Null

Write-Host "==== 客户视角全走查 ====`n"

# 1. 门户首页
Write-Host '[1] 门户首页'
$portalFile = Join-Path $temp 'home.html'
$code = & curl.exe -s -o $portalFile -w '%{http_code}' "$Base/"
$text = Get-Content -LiteralPath $portalFile -Raw

if ($code -eq '200') { Pass "首页 200（$([math]::Round($text.Length / 1KB, 1)) KB）" } else { Fail "首页 $code" }

$cardCount = ([regex]::Matches($text, 'class="p-card"')).Count
if ($cardCount -eq 5) { Pass '首页 5 张产品卡' } else { Fail "首页卡片数量 $cardCount（应为 5）" }

foreach ($p in $products) {
    if ($text -match ('/' + $p.Code + '/preview')) { Pass ("{0,-5} 卡片链到 /{0}/preview" -f $p.Code) }
    else { Fail ("{0,-5} 首页没有链到 preview" -f $p.Code) }
}

# 2. 产品介绍页
Write-Host "`n[2] 五个产品介绍页"
foreach ($p in $products) {
    $f = Join-Path $temp ($p.Code + '-preview.html')
    $c = & curl.exe -s -o $f -w '%{http_code}' "$Base/$($p.Code)/preview"
    $t = Get-Content -LiteralPath $f -Raw

    $hasName = $t -match $p.Name
    $hasSuited = $t -match '<h3>适合谁</h3>'
    $hasLoginLink = $t -match ('/' + $p.Code + '/login')

    if ($c -eq '200' -and $hasName -and $hasSuited -and $hasLoginLink) {
        Pass ("{0,-5} preview 200 / 标题 / 适合谁 / 登录入口" -f $p.Code)
    } else {
        Fail ("{0,-5} preview $c 标题=$hasName 适合谁=$hasSuited 登录入口=$hasLoginLink" -f $p.Code)
    }
}

# 3. 登录 + 主页面
Write-Host "`n[3] 五个登录页与登入后主页面"
foreach ($p in $products) {
    $cookie = Join-Path $temp ($p.Code + '-ck.txt')
    $loginHtml = Join-Path $temp ($p.Code + '-login.html')

    $c = & curl.exe -s -c $cookie -o $loginHtml -w '%{http_code}' "$Base/$($p.Code)/login"
    if ($c -ne '200') { Fail ("{0,-5} login {1}" -f $p.Code, $c); continue }

    $token = [regex]::Match((Get-Content -LiteralPath $loginHtml -Raw), 'name="_token" value="([^"]+)"').Groups[1].Value
    if ([string]::IsNullOrWhiteSpace($token)) { Fail ("{0,-5} 取不到 token" -f $p.Code); continue }

    $post = & curl.exe -s -b $cookie -c $cookie -o (Join-Path $temp ($p.Code + '-post.html')) -w '%{http_code}' `
        -X POST "$Base/$($p.Code)/login" `
        --data-urlencode "_token=$token" `
        --data-urlencode "$($p.Field)=$($p.Account)" `
        --data-urlencode "password=$demoPass"

    $homeHtml = Join-Path $temp ($p.Code + '-home.html')
    $h = & curl.exe -s -b $cookie -o $homeHtml -w '%{http_code}' "$Base/$($p.Home)"
    $ht = Get-Content -LiteralPath $homeHtml -Raw

    if ($h -eq '200' -and $ht.Length -gt 500) {
        Pass ("{0,-5} 登入成功 → {1} 200（{2} KB）" -f $p.Code, $p.Home, [math]::Round($ht.Length / 1KB, 1))
    } else {
        Fail ("{0,-5} 登入后 {1} 返回 {2}（POST {3}）" -f $p.Code, $p.Home, $h, $post)
    }
}

Remove-Item -LiteralPath $temp -Recurse -Force -ErrorAction SilentlyContinue

Write-Host ''
Write-Host ("==== 汇总：PASS {0} / FAIL {1} ====" -f $pass, $fail)

exit ([int]($fail -gt 0))

