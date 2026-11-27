# 各产品"每个角色账号登录后侧栏是否有内容"巡检
# 用法：pwsh -File tools\check-role-nav.ps1 -Product mes
# 注意：登录路由限流 5 次/分钟/IP，脚本每 4 次登录清一次缓存。
param(
    [string]$Base = 'http://127.0.0.1:8201',
    [string]$Php = 'C:\tools\php83\php.exe',
    [string]$Product = ''
)

Set-Location 'E:\demo-website'

$fields = @{ erp = 'account'; pm = 'username'; shop = 'username'; mes = 'account'; hr = 'account' }
$landings = @{ erp = '/erp/home'; pm = '/pm/dashboard'; shop = '/shop/dashboard'; mes = '/mes/dashboard'; hr = '/hr/dashboard' }

$targets = if ($Product) { @($Product) } else { @('erp', 'pm', 'shop', 'mes', 'hr') }
$total = 0
$empty = @()

foreach ($code in $targets) {
    $field = $fields[$code]
    $homePath = $landings[$code]
    $list = (& $Php tools/list-users.php $code) | ConvertFrom-Json
    Write-Output "===== $code ($($list.Count) 个账号) ====="
    $i = 0
    foreach ($acc in $list) {
        $i++
        if ($i % 4 -eq 1) { & $Php artisan cache:clear 2>&1 | Out-Null }

        $lg = Invoke-WebRequest "$Base/$code/login" -SessionVariable s -UseBasicParsing
        $tk = ([regex]'name="_token" value="([^"]+)"').Match($lg.Content).Groups[1].Value
        $body = @{ _token = $tk; password = '123456' }
        $body[$field] = $acc.email
        try {
            Invoke-WebRequest "$Base/$code/login" -Method Post -Body $body -WebSession $s -UseBasicParsing -MaximumRedirection 5 | Out-Null
        } catch {
            $msg = $_.Exception.Message
            Write-Output ("{0,-14} 登录异常: {1}" -f $acc.username, $msg.Substring(0, [Math]::Min(50, $msg.Length)))
            continue
        }

        $r = Invoke-WebRequest ($Base + $homePath) -WebSession $s -UseBasicParsing -SkipHttpErrorCheck -MaximumRedirection 5
        $nav = ([regex]::Matches($r.Content, 'href="[^"]*/' + $code + '/(?!assets)')).Count
        if ($nav -eq 0) { $nav = ([regex]::Matches($r.Content, 'class="[^"]*nav-link')).Count }
        $total++
        if ($nav -le 1) { $empty += "$code/$($acc.username)" }
        "{0,-16} {1,-14} role={2,-26} status={3} 菜单项={4}" -f $acc.username, $acc.name, ($acc.roles -join ','), $r.StatusCode, $nav
    }
}

Write-Output ''
Write-Output "巡检账号 $total 个，侧栏为空/异常 $($empty.Count) 个：$($empty -join '、')"
