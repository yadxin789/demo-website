$ErrorActionPreference = 'Continue'
$script:Hits = @{}
function AddResult { param($Id) $script:Results = 1 }
$script:Hits['a'] = 1
Write-Output ('diag: ' + ($script:Hits['a']))
