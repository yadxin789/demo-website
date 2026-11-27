param(
    [string]$Php = 'C:\tools\php83\php.exe'
)

Set-Location 'E:\demo-website'

$targets = @(
    @{ p='erp'; uid=30; uris=@('admin','admin/users','admin/roles','admin/ops','admin/settings','admin/departments','admin/positions','admin/ai-guide','admin/master-data','admin/permissions') },
    @{ p='pm'; uid=1; uris=@('admin','admin/users','admin/departments','admin/roles','admin/positions','admin/ai-guide','admin/ops','admin/settings') },
    @{ p='shop'; uid=1; uris=@('admin','admin/users','admin/departments','admin/positions','admin/roles','admin/ai-guide','admin/ops','admin/settings') },
    @{ p='mes'; uid=1; uris=@('admin','admin/users','admin/departments','admin/positions','admin/roles','admin/ai-guide','admin/ops','admin/settings','system/users','system/roles') },
    @{ p='hr'; uid=1; uris=@('admin','admin/users','admin/departments','admin/positions','admin/roles','admin/ai-guide','admin/ops','admin/settings','system/users','org/departments') }
)

foreach ($t in $targets) {
    foreach ($uri in $t.uris) {
        $full = "$($t.p)/$uri"
        $out = & $Php tools\probe-route.php $full $t.uid out $t.p 2>&1
        $first = ($out | Select-Object -First 3) -join ' | '
        if ($first -match 'OK LEN') {
            $len = [regex]::Match($first, 'OK LEN (\d+)').Groups[1].Value
            Write-Output ("OK   {0,-34} len={1}" -f $full, $len)
        } else {
            Write-Output ("FAIL {0,-34} {1}" -f $full, ($out | Select-Object -First 2) -join ' / ')
        }
    }
}
