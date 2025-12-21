if (-not $env:GITHUB_TOKEN) { Write-Host 'GITHUB_TOKEN not set'; exit 2 }
$token = $env:GITHUB_TOKEN
$headers = @{ Authorization = "token $token"; Accept = 'application/vnd.github.v3+json' }
$body = @{ default_branch = 'project-main' } | ConvertTo-Json
$resp = Invoke-RestMethod -Method PATCH -Uri 'https://api.github.com/repos/mert6148/User-login' -Headers $headers -Body $body
Write-Host "Default branch set to: $($resp.default_branch)"
$verify = Invoke-RestMethod -Uri 'https://api.github.com/repos/mert6148/User-login' -Headers $headers
Write-Host "Verify: $($verify.default_branch)"
