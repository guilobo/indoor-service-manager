param(
    [string] $VmConfigDir = "D:\ProgramData\oracle\pamis2",
    [string] $Branch = "main",
    [string] $RemoteName = "oracle",
    [string] $BareRepository = "/srv/git/indoor-service-manager.git"
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

$remoteUrl = "ubuntu@${hostName}:$BareRepository"
$keyPathForGit = $keyPath -replace '\\', '/'
$sshCommand = "ssh -i `"$keyPathForGit`" -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new"

git config "remote.$RemoteName.url" $remoteUrl
git config "remote.$RemoteName.fetch" "+refs/heads/*:refs/remotes/$RemoteName/*"
git config core.sshCommand $sshCommand

git push $RemoteName "${Branch}:$Branch"

if ($LASTEXITCODE -ne 0) {
    throw "Git deploy failed with exit code $LASTEXITCODE"
}

Write-Host "DEPLOY_OK git push $RemoteName $Branch"
