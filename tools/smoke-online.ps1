$ErrorActionPreference = 'Continue'
$base = 'http://123.207.233.94'

function Get-Page($path, $needles) {
    try {
        $r = Invoke-WebRequest -Uri "$base$path" -UseBasicParsing -TimeoutSec 20
        $hit = @()
        foreach ($n in $needles) { if ($r.Content -match [regex]::Escape($n)) { $hit += $n } }
        return ("{0,-22} {1} len={2} 命中={3}" -f $path, $r.StatusCode, $r.Content.Length, ($hit -join ',')), $r.Content
    } catch {
        return ("{0,-22} ERR {1}" -f $path, $_.Exception.Message), ''
    }
}

$paths = @(
    @{p = '/'; n = @('电子制造 ERP', '研发项目管理', '百货零售 ERP', '智能制造 MES', '人事管理 HR', 'yadxin789') },
    @{p = '/erp/'; n = @('电子制造 ERP') },
    @{p = '/pm/'; n = @('研发项目管理') },
    @{p = '/shop/'; n = @('百货零售 ERP') },
    @{p = '/mes/'; n = @('智能制造 MES') },
    @{p = '/hr/'; n = @('人事管理 HR') },
    @{p = '/erp/login'; n = @('邮箱或用户名') },
    @{p = '/pm/login'; n = @('邮箱或用户名') },
    @{p = '/shop/login'; n = @('邮箱或用户名') },
    @{p = '/mes/login'; n = @('邮箱或用户名') },
    @{p = '/hr/login'; n = @('邮箱或用户名') }
)

$homeBody = ''
foreach ($item in $paths) {
    $line, $body = Get-Page $item.p $item.n
    Write-Output $line
    if ($item.p -eq '/') { $homeBody = $body }
}

Write-Output ("首页仍含旧文案「一个站点，5 套独立系统」={0}" -f ($homeBody -match '一个站点'))
Write-Output ("首页含备案位={0}，含微信={1}" -f ($homeBody -match '备案'), ($homeBody -match 'yadxin789'))

