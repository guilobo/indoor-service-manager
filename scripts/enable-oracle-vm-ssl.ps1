param(
    [string] $VmConfigDir = "D:\ProgramData\oracle\pamis2"
)

$ErrorActionPreference = "Stop"

function Get-RequiredFileContent {
    param([string] $Path)

    if (-not (Test-Path $Path)) {
        throw "Required file not found: $Path"
    }

    return (Get-Content -Raw $Path).Trim()
}

$hostName = Get-RequiredFileContent (Join-Path $VmConfigDir "ip.txt")
$keyPath = Join-Path $VmConfigDir "ssh-key-2026-03-31.key"

if (-not (Test-Path $keyPath)) {
    throw "SSH key not found: $keyPath"
}

ssh -i $keyPath -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new "ubuntu@$hostName" "sudo /usr/local/sbin/indoor-issue-ssl"

if ($LASTEXITCODE -ne 0) {
    throw "SSL setup failed with exit code $LASTEXITCODE"
}

Write-Host "SSL_OK"
