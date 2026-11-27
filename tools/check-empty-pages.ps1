# 空列表页巡检：以管理员登录某产品，逐页抓取无参 GET 页面，统计表格数据行。
# 用法：pwsh -File tools\check-empty-pages.ps1 -Product erp
# 输出：按「数据行 <= 阈值」列出疑似空列表页，供补种子数据。
param(
    [string]$Base = 'http://127.0.0.1:8201',
    [string]$Php = 'C:\tools\php83\php.exe',
    [string]$Product = 'erp',
    [int]$MinTd = 8,
    [string]$Out = ''
)

Set-Location 'E:\demo-website'

$fields = @{ erp = 'account'; pm = 'username'; shop = 'username'; mes = 'account'; hr = 'account' }
$admins = @{
    erp  = 'admin@erp.demo'
    pm   = 'admin@pm.demo'
    shop = 'admin@shop.demo'
    mes  = 'admin@mes.demo'
    hr   = 'admin@hr.demo'
}

$routes = & $Php artisan route:list --json 2>$null | ConvertFrom-Json
$pages = $routes |
    Where-Object {
        ($_.method -join '|') -match 'GET' -and
        $_.uri -match "^$Product/" -and
        $_.uri -notmatch '\{' -and
        $_.uri -notmatch '/api/' -and
        $_.uri -notmatch '(create|edit|print|export|download|logout|login)$'
    } |
    Select-Object -ExpandProperty uri -Unique

Write-Output "产品 $Product 待巡检页面 $($pages.Count) 个"

& $Php artisan cache:clear 2>&1 | Out-Null
$lg = Invoke-WebRequest "$Base/$Product/login" -SessionVariable s -UseBasicParsing
$tk = ([regex]'name="_token" value="([^"]+)"').Match($lg.Content).Groups[1].Value
$body = @{ _token = $tk; password = '123456' }
$body[$fields[$Product]] = $admins[$Product]
Invoke-WebRequest "$Base/$Product/login" -Method Post -Body $body -WebSession $s -UseBasicParsing -MaximumRedirection 5 | Out-Null

$rows = @()
foreach ($u in $pages) {
    $url = "$Base/$u"
    try {
        $r = Invoke-WebRequest $url -WebSession $s -UseBasicParsing -SkipHttpErrorCheck -MaximumRedirection 5
    } catch {
        $rows += [pscustomobject]@{ uri = $u; status = 'ERR'; td = 0; note = $_.Exception.Message.Substring(0, [Math]::Min(40, $_.Exception.Message.Length)) }
        continue
    }
    $td = ([regex]::Matches($r.Content, '<td')).Count
    $emptyWords = ([regex]::Matches($r.Content, '暂无数据|暂无记录|暂无|没有数据|无数据|empty-state|data-empty')).Count
    $rows += [pscustomobject]@{ uri = $u; status = [int]$r.StatusCode; td = $td; note = "空态词=$emptyWords 长度=$($r.Content.Length)" }
}

if ($Out) { $rows | ConvertTo-Json -Depth 4 | Set-Content $Out -Encoding UTF8 }

$bad = $rows | Where-Object { $_.status -eq 200 -and $_.td -lt $MinTd }
Write-Output ''
Write-Output "疑似空列表（td < $MinTd）：$($bad.Count) 个"
$bad | ForEach-Object { "{0,-46} td={1,-4} {2}" -f $_.uri, $_.td, $_.note }
Write-Output ''
$err = $rows | Where-Object { $_.status -ne 200 }
Write-Output "非 200 页面：$($err.Count) 个"
$err | ForEach-Object { "{0,-46} status={1}" -f $_.uri, $_.status }
