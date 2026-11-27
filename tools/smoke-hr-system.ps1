$ErrorActionPreference = 'Stop'
$base = 'http://127.0.0.1:8201'
$s = New-Object Microsoft.PowerShell.Commands.WebRequestSession

function Tok($html) { [regex]::Match($html, 'name="_token" value="([^"]+)"').Groups[1].Value }

function Remove-Smoke($sess) {
    $page = Invoke-WebRequest -Uri "$base/hr/system/users" -WebSession $sess -UseBasicParsing
    $m = [regex]::Match($page.Content, '(?s)qa_hr.*?/hr/system/users/(\d+)/edit')
    if (-not $m.Success) { return }
    Invoke-WebRequest -Uri "$base/hr/system/users/$($m.Groups[1].Value)" -Method Post -WebSession $sess -UseBasicParsing -Body @{
        _token = (Tok $page.Content); _method = 'DELETE'
    } | Out-Null
    Write-Output "已清理残留 HR 验收账号 id=$($m.Groups[1].Value)"
}

$login = Invoke-WebRequest -Uri "$base/hr/login" -WebSession $s -UseBasicParsing
Invoke-WebRequest -Uri "$base/hr/login" -Method Post -WebSession $s -UseBasicParsing -Body @{
    _token = (Tok $login.Content); account = 'admin'; password = '123456'
} | Out-Null

foreach ($p in @('/hr/system/users', '/hr/system/users/create', '/hr/system/roles', '/hr/org/departments', '/hr/org/positions', '/hr/org/headcounts')) {
    $r = Invoke-WebRequest -Uri "$base$p" -WebSession $s -UseBasicParsing
    Write-Output ("{0,-26} {1} len={2}" -f $p, $r.StatusCode, $r.Content.Length)
}

Remove-Smoke $s

$create = Invoke-WebRequest -Uri "$base/hr/system/users/create" -WebSession $s -UseBasicParsing
$post = Invoke-WebRequest -Uri "$base/hr/system/users" -Method Post -WebSession $s -UseBasicParsing -Body @{
    _token = (Tok $create.Content)
    name = '验收 HR 专员'
    username = 'qa_hr'
    email = 'qa_hr@hr.demo'
    employee_no = 'HR-9001'
    role = 'hr_specialist'
    password = 'Smoke123456'
    status = 'active'
}
Write-Output ("新建落点 {0}，含 qa_hr={1}，含提示={2}" -f $post.BaseResponse.RequestMessage.RequestUri.AbsolutePath, ($post.Content -match 'qa_hr'), ($post.Content -match '已新增账号'))

# 新账号用邮箱登录
$s2 = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$l2 = Invoke-WebRequest -Uri "$base/hr/login" -WebSession $s2 -UseBasicParsing
Invoke-WebRequest -Uri "$base/hr/login" -Method Post -WebSession $s2 -UseBasicParsing -Body @{
    _token = (Tok $l2.Content); account = 'qa_hr@hr.demo'; password = 'Smoke123456'
} | Out-Null
$dash = Invoke-WebRequest -Uri "$base/hr/dashboard" -WebSession $s2 -UseBasicParsing
Write-Output ("新账号邮箱登录 dashboard {0} len={1}" -f $dash.StatusCode, $dash.Content.Length)

$list = Invoke-WebRequest -Uri "$base/hr/system/users" -WebSession $s -UseBasicParsing
$m = [regex]::Match($list.Content, '(?s)qa_hr.*?/hr/system/users/(\d+)/edit')
if ($m.Success) {
    $id = $m.Groups[1].Value
    $edit = Invoke-WebRequest -Uri "$base/hr/system/users/$id/edit" -WebSession $s -UseBasicParsing
    Write-Output ("编辑页 {0} len={1}" -f $edit.StatusCode, $edit.Content.Length)
    Invoke-WebRequest -Uri "$base/hr/system/users/$id" -Method Post -WebSession $s -UseBasicParsing -Body @{
        _token = (Tok $list.Content); _method = 'DELETE'
    } | Out-Null
    $after = Invoke-WebRequest -Uri "$base/hr/system/users" -WebSession $s -UseBasicParsing
    Write-Output ("删除后仍有 qa_hr={0}" -f ($after.Content -match 'qa_hr'))
}
