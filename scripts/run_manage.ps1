param(
    [Parameter(ValueFromRemainingArguments=$true)]
    [string[]]$Args
)

# Wrapper to run the PHP management script easily from PowerShell
$php = 'php'
$script = Join-Path -Path $PSScriptRoot -ChildPath 'manage_users.php'

if (-not (Get-Command $php -ErrorAction SilentlyContinue)) {
    Write-Error "PHP executable not found in PATH. Install PHP or add it to PATH."
    exit 1
}

# Forward all args to the PHP script
& $php $script @Args