$ErrorActionPreference = 'Stop'
$base = 'http://127.0.0.1:8201'
$s = New-Object Microsoft.PowerShell.Commands.WebRequestSession

function Tok($html) { [regex]::Match($html, 'name="_token" value="([^"]+)"').Groups[1].Value }

# 清理上次残留的验收账号
function Remove-Smoke($sess) {
    $page = Invoke-WebRequest -Uri "$base/mes/system/users" -WebSession $sess -UseBasicParsing
    $m = [regex]::Match($page.Content, '(?s)qa_smoke.*?/mes/system/users/(\d+)/edit')
    if (-not $m.Success) { return $false }
    $id = $m.Groups[1].Value
    Invoke-WebRequest -Uri "$base/mes/system/users/$id" -Method Post -WebSession $sess -UseBasicParsing -Body @{
        _token = (Tok $page.Content); _method = 'DELETE'
    } | Out-Null
    Write-Output "已清理残留验收账号 id=$id"
    return $true
}

$login = Invoke-WebRequest -Uri "$base/mes/login" -WebSession $s -UseBasicParsing
Invoke-WebRequest -Uri "$base/mes/login" -Method Post -WebSession $s -UseBasicParsing -Body @{
    _token = (Tok $login.Content); account = 'admin'; password = '123456'
} | Out-Null

foreach ($p in @('/mes/system/departments', '/mes/system/positions', '/mes/system/users', '/mes/system/users/create')) {
    $r = Invoke-WebRequest -Uri "$base$p" -WebSession $s -UseBasicParsing
    $hit = @('部门数', '岗位数', '账号列表', '新增账号') | Where-Object { $r.Content -match $_ }
    Write-Output ("{0,-28} {1} len={2} 关键词={3}" -f $p, $r.StatusCode, $r.Content.Length, ($hit -join '/'))
}

Remove-Smoke $s | Out-Null

$create = Invoke-WebRequest -Uri "$base/mes/system/users/create" -WebSession $s -UseBasicParsing
$post = Invoke-WebRequest -Uri "$base/mes/system/users" -Method Post -WebSession $s -UseBasicParsing -Body @{
    _token = (Tok $create.Content)
    name = '验收 测试员'
    username = 'qa_smoke'
    email = 'qa_smoke@mes.demo'
    employee_no = 'JG-9001'
    department_id = '3'
    position_id = '4'
    role = 'inspector'
    password = 'Smoke123456'
    status = 'active'
}
Write-Output ("新建后落点 {0}，含 qa_smoke={1}，含 flash 提示={2}" -f $post.BaseResponse.RequestMessage.RequestUri.AbsolutePath, ($post.Content -match 'qa_smoke'), ($post.Content -match '已新增账号'))

$list = Invoke-WebRequest -Uri "$base/mes/system/users" -WebSession $s -UseBasicParsing
Write-Output ("列表页 {0}，含 qa_smoke={1}" -f $list.StatusCode, ($list.Content -match 'qa_smoke'))

# 新账号用邮箱登录
$s2 = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$l2 = Invoke-WebRequest -Uri "$base/mes/login" -WebSession $s2 -UseBasicParsing
Invoke-WebRequest -Uri "$base/mes/login" -Method Post -WebSession $s2 -UseBasicParsing -Body @{
    _token = (Tok $l2.Content); account = 'qa_smoke@mes.demo'; password = 'Smoke123456'
} | Out-Null
$dash = Invoke-WebRequest -Uri "$base/mes/dashboard" -WebSession $s2 -UseBasicParsing
Write-Output ("新账号邮箱登录 dashboard {0} len={1}" -f $dash.StatusCode, $dash.Content.Length)

# 编辑页 + 部门新增/改名/删除
$m = [regex]::Match($list.Content, '(?s)qa_smoke.*?/mes/system/users/(\d+)/edit')
if ($m.Success) {
    $id = $m.Groups[1].Value
    $edit = Invoke-WebRequest -Uri "$base/mes/system/users/$id/edit" -WebSession $s -UseBasicParsing
    Write-Output ("编辑页 {0} len={1}" -f $edit.StatusCode, $edit.Content.Length)
    Remove-Smoke $s | Out-Null
    $after = Invoke-WebRequest -Uri "$base/mes/system/users" -WebSession $s -UseBasicParsing
    Write-Output ("删除后仍有 qa_smoke={0}" -f ($after.Content -match 'qa_smoke'))
} else {
    Write-Output '编辑/删除段：未找到 qa_smoke id'
}

$dept = Invoke-WebRequest -Uri "$base/mes/system/departments" -WebSession $s -UseBasicParsing
Invoke-WebRequest -Uri "$base/mes/system/departments" -Method Post -WebSession $s -UseBasicParsing -Body @{
    _token = (Tok $dept.Content); name = '验收临时部门'; parent_id = ''; sort = '99'; status = 'active'
} | Out-Null
$dept2 = Invoke-WebRequest -Uri "$base/mes/system/departments" -WebSession $s -UseBasicParsing
$dm = [regex]::Match($dept2.Content, '(?s)验收临时部门</b>.*?/mes/system/departments/(\d+)')
Write-Output ("部门新增后含临时部门={0}，抓到 id={1}" -f ($dept2.Content -match '验收临时部门'), $dm.Groups[1].Value)
if ($dm.Success) {
    Invoke-WebRequest -Uri "$base/mes/system/departments/$($dm.Groups[1].Value)" -Method Post -WebSession $s -UseBasicParsing -Body @{
        _token = (Tok $dept2.Content); _method = 'DELETE'
    } | Out-Null
    $dept3 = Invoke-WebRequest -Uri "$base/mes/system/departments" -WebSession $s -UseBasicParsing
    Write-Output ("部门删除后仍有临时部门={0}" -f ($dept3.Content -match '验收临时部门'))
}
