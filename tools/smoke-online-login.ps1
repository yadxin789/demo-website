$ErrorActionPreference = 'Continue'
$base = 'http://123.207.233.94'

function Login($product, $field, $account, $password) {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $r = Invoke-WebRequest -Uri "$base/$product/login" -WebSession $s -UseBasicParsing -TimeoutSec 20
    $tok = [regex]::Match($r.Content, 'name="_token" value="([^"]+)"').Groups[1].Value
    $body = @{ _token = $tok; password = $password }
    $body[$field] = $account
    $p = Invoke-WebRequest -Uri "$base/$product/login" -Method Post -WebSession $s -UseBasicParsing -TimeoutSec 20 -Body $body
    return $s, $p.BaseResponse.RequestMessage.RequestUri.AbsolutePath
}

$cases = @(
    @{ p = 'erp';  f = 'account';  a = 'admin@erp.demo';  n = '/erp/home';      t = '工作台' },
    @{ p = 'erp';  f = 'account';  a = 'admin';           n = '/erp/home';      t = '工作台' },
    @{ p = 'pm';   f = 'username'; a = 'admin@pm.demo';   n = '/pm/dashboard';  t = '项目' },
    @{ p = 'pm';   f = 'username'; a = 'admin';           n = '/pm/dashboard';  t = '项目' },
    @{ p = 'shop'; f = 'username'; a = 'admin@shop.demo'; n = '/shop/dashboard'; t = 'POS' },
    @{ p = 'shop'; f = 'username'; a = 'admin';           n = '/shop/dashboard'; t = 'POS' },
    @{ p = 'mes';  f = 'account';  a = 'admin@mes.demo';  n = '/mes/dashboard'; t = '工作台' },
    @{ p = 'mes';  f = 'account';  a = 'admin';           n = '/mes/dashboard'; t = '工作台' },
    @{ p = 'hr';   f = 'account';  a = 'admin@hr.demo';   n = '/hr/dashboard';  t = '人力' },
    @{ p = 'hr';   f = 'account';  a = 'admin';           n = '/hr/dashboard';  t = '人力' }
)

foreach ($c in $cases) {
    $s, $land = Login $c.p $c.f $c.a '123456'
    $ok = ($land -eq $c.n)
    $r = Invoke-WebRequest -Uri "$base$($c.n)" -WebSession $s -UseBasicParsing -TimeoutSec 20
    Write-Output ("{0,-5} {1,-16} 落点={2,-18} 正确={3} 目标页={4} len={5} 命中={6}" -f $c.p, $c.a, $land, $ok, $r.StatusCode, $r.Content.Length, ($r.Content -match [regex]::Escape($c.t)))
}
