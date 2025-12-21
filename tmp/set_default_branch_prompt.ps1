# Prompts for a GitHub PAT, sets the repo default branch, then clears the token
$secure = Read-Host -Prompt 'GITHUB_TOKEN (paste token and press Enter) (input hidden)' -AsSecureString
$token = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))
$headers = @{ Authorization = "token $token"; Accept = 'application/vnd.github.v3+json' }
$body = @{ default_branch = 'project-main' } | ConvertTo-Json
try {
    $resp = Invoke-RestMethod -Method PATCH -Uri 'https://api.github.com/repos/mert6148/User-login' -Headers $headers -Body $body
    Write-Host "Default branch set to: $($resp.default_branch)"
    $verify = Invoke-RestMethod -Uri 'https://api.github.com/repos/mert6148/User-login' -Headers $headers
    Write-Host "Verify: $($verify.default_branch)"
} catch {
    Write-Error $_.Exception.Message
}
# clear
Remove-Variable token -ErrorAction SilentlyContinue
[Runtime.InteropServices.Marshal]::ZeroFreeBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))
Write-Host 'Done. Token was not retained.'
