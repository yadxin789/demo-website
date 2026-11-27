$ErrorActionPreference = 'Continue'
$base = 'http://123.207.233.94'

function Login($product, $field, $account, $password) {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $r = Invoke-WebRequest -Uri "$base/$product/login" -WebSession $s -UseBasicParsing -TimeoutSec 20
    $tok = [regex]::Match($r.Content, 'name="_token" value="([^"]+)"').Groups[1].Value
    $body = @{ _token = $tok; password = $password }
    $body[$field] = $account
    $p = Invoke-WebRequest -Uri "$base/$product/login" -Method Post -WebSession $s -UseBasicParsing -TimeoutSec 20 -Body $body
    return $s, $p.StatusCode, $p.BaseResponse.RequestMessage.RequestUri.AbsolutePath
}

function Probe($session, $path, $needle) {
    try {
        $r = Invoke-WebRequest -Uri "$base$path" -WebSession $session -UseBasicParsing -TimeoutSec 20
        return ("{0,-26} {1} len={2} 命中「{3}」={4}" -f $path, $r.StatusCode, $r.Content.Length, $needle, ($r.Content -match [regex]::Escape($needle)))
    } catch {
        return ("{0,-26} ERR {1}" -f $path, $_.Exception.Message)
    }
}

# ---- MES：用户名登录 + 邮箱登录 ----
$s1, $c1, $u1 = Login 'mes' 'account' 'admin' '123456'
Write-Output ("MES 用户名登录 {0} → {1}" -f $c1, $u1)
foreach ($p in @(@('/mes/system/departments', '部门数'), @('/mes/system/positions', '岗位数'), @('/mes/system/users', '账号列表'), @('/mes/system/roles', '角色'), @('/mes/dashboard', '工作台'))) {
    Write-Output (Probe $s1 $p[0] $p[1])
}

$s2, $c2, $u2 = Login 'mes' 'account' 'admin@mes.demo' '123456'
Write-Output ("MES 邮箱登录 {0} → {1}" -f $c2, $u2)
Write-Output (Probe $s2 '/mes/dashboard' '工作台')

# ---- HR：邮箱登录 + 后台页 ----
$s3, $c3, $u3 = Login 'hr' 'account' 'admin@hr.demo' '123456'
Write-Output ("HR 邮箱登录 {0} → {1}" -f $c3, $u3)
foreach ($p in @(@('/hr/system/users', '账号'), @('/hr/system/users/create', '新增账号'), @('/hr/system/roles', '角色'), @('/hr/org/departments', '部门'), @('/hr/org/positions', '岗位'), @('/hr/dashboard', '人力'))) {
    Write-Output (Probe $s3 $p[0] $p[1])
}

# ---- PM / ERP / Shop：邮箱登录一次 ----
$s4, $c4, $u4 = Login 'pm' 'username' 'admin@pm.demo' '123456'
Write-Output ("PM 邮箱登录 {0} → {1}" -f $c4, $u4)
Write-Output (Probe $s4 '/pm/dashboard' '项目')

$s5, $c5, $u5 = Login 'shop' 'username' 'admin@shop.demo' '123456'
Write-Output ("Shop 邮箱登录 {0} → {1}" -f $c5, $u5)
Write-Output (Probe $s5 '/shop/dashboard' 'POS')

Write-Output "（ERP 登录留到最后单独测，避免与限流冲突）"
