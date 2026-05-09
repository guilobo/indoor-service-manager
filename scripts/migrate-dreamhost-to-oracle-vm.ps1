param(
    [string] $DreamHostConfigPath = ".serverconfig",
    [string] $VmConfigDir = "D:\ProgramData\oracle\pamis2",
    [string] $AdminEmail = "contato@indoortech.com.br",
    [string] $AdminPassword = ""
)

$ErrorActionPreference = "Stop"

function Read-KeyValueConfig {
    param([string] $Path)

    if (-not (Test-Path $Path)) {
        throw "Config file not found: $Path"
    }

    $pairs = @()

    foreach ($line in Get-Content $Path) {
        if ($line -match '^\s*$' -or $line -match '^\s*#') {
            continue
        }

        $parts = $line.Split(':', 2)

        if ($parts.Count -ne 2) {
            continue
        }

        $pairs += [pscustomobject]@{
            Key = $parts[0].Trim()
            Value = $parts[1].Trim()
        }
    }

    return $pairs
}

function Get-ConfigValue {
    param(
        [array] $Pairs,
        [string[]] $Names,
        [int] $DuplicateIndex = 0
    )

    foreach ($name in $Names) {
        $matches = @($Pairs | Where-Object { $_.Key -eq $name })

        if ($matches.Count -gt $DuplicateIndex) {
            return $matches[$DuplicateIndex].Value
        }
    }

    return ""
}

function Get-RequiredFileContent {
    param([string] $Path)

    if (-not (Test-Path $Path)) {
        throw "Required file not found: $Path"
    }

    return (Get-Content -Raw $Path).Trim()
}

function Escape-SingleQuotedShell {
    param([string] $Value)

    return $Value -replace "'", "'\''"
}

function Invoke-DreamHostScript {
    param(
        [string] $HostName,
        [string] $SshUser,
        [string] $SshPass,
        [string] $Script
    )

    $plinkCandidate = "D:\programas\Putty\plink.exe"
    $pscpCandidate = "D:\programas\Putty\pscp.exe"
    $plinkPath = if (Test-Path $plinkCandidate) { $plinkCandidate } else { "plink.exe" }
    $pscpPath = if (Test-Path $pscpCandidate) { $pscpCandidate } else { "pscp.exe" }
    $localScriptPath = Join-Path ([System.IO.Path]::GetTempPath()) ("dreamhost-db-dump-{0}.sh" -f ([guid]::NewGuid().ToString("N")))
    $remoteScriptPath = "/home/$SshUser/.dreamhost-db-dump-$([guid]::NewGuid().ToString("N")).sh"

    try {
        $utf8WithoutBom = New-Object System.Text.UTF8Encoding($false)
        [System.IO.File]::WriteAllText($localScriptPath, $Script, $utf8WithoutBom)

        & $pscpPath -batch -pw $SshPass $localScriptPath "${SshUser}@${HostName}:$remoteScriptPath" | Out-Null

        if ($LASTEXITCODE -ne 0) {
            throw "DreamHost script upload failed with exit code $LASTEXITCODE"
        }

        & $plinkPath -ssh $HostName -l $SshUser -pw $SshPass "chmod 700 $remoteScriptPath" | Out-Null

        if ($LASTEXITCODE -ne 0) {
            throw "DreamHost chmod failed with exit code $LASTEXITCODE"
        }

        $output = & $plinkPath -ssh $HostName -l $SshUser -pw $SshPass "bash $remoteScriptPath; status=`$?; rm -f $remoteScriptPath; exit `$status"

        if ($LASTEXITCODE -ne 0) {
            throw "DreamHost dump failed with exit code $LASTEXITCODE"
        }

        return ($output | Select-Object -Last 1).Trim()
    } finally {
        if (Test-Path $localScriptPath) {
            Remove-Item $localScriptPath -Force
        }
    }
}

$pairs = Read-KeyValueConfig $DreamHostConfigPath
$dreamHostName = Get-ConfigValue $pairs @("host", "ssh_host")
$dreamSshUser = Get-ConfigValue $pairs @("ssh_user", "user")
$dreamSshPass = Get-ConfigValue $pairs @("ssh_pass", "pass")
$dreamDbUsername = Get-ConfigValue $pairs @("db_user", "mysql_user", "user") 1
$dreamDbPassword = Get-ConfigValue $pairs @("db_pass", "mysql_pass", "pass") 1
$dreamDbDatabase = Get-ConfigValue $pairs @("db", "database", "db_database")
$dreamDbHost = Get-ConfigValue $pairs @("db_host", "mysql_host", "server")

if (-not $dreamHostName -or -not $dreamSshUser -or -not $dreamSshPass -or -not $dreamDbUsername -or -not $dreamDbPassword -or -not $dreamDbDatabase -or -not $dreamDbHost) {
    throw "Missing required DreamHost values in $DreamHostConfigPath"
}

$vmHostName = Get-RequiredFileContent (Join-Path $VmConfigDir "ip.txt")
$vmKeyPath = Join-Path $VmConfigDir "ssh-key-2026-03-31.key"

if (-not (Test-Path $vmKeyPath)) {
    throw "SSH key not found: $vmKeyPath"
}

$dumpScript = @'
set -euo pipefail

DB_HOST='__DB_HOST__'
DB_NAME='__DB_NAME__'
DB_USER='__DB_USER__'
DB_PASSWORD='__DB_PASSWORD__'
DUMP_PATH="$HOME/indoor-service-manager-$(date +%Y%m%d%H%M%S).sql.gz"

if ! MYSQL_PWD="$DB_PASSWORD" mysqldump \
    --host="$DB_HOST" \
    --user="$DB_USER" \
    --single-transaction \
    --quick \
    --no-tablespaces \
    --routines \
    --triggers \
    --events \
    "$DB_NAME" | gzip -9 > "$DUMP_PATH"; then
    MYSQL_PWD="$DB_PASSWORD" mysqldump \
        --host="$DB_HOST" \
        --user="$DB_USER" \
        --single-transaction \
        --quick \
        --no-tablespaces \
        --routines \
        --triggers \
        "$DB_NAME" | gzip -9 > "$DUMP_PATH"
fi

echo "$DUMP_PATH"
'@

$dumpScript = $dumpScript.
    Replace("__DB_HOST__", (Escape-SingleQuotedShell $dreamDbHost)).
    Replace("__DB_NAME__", (Escape-SingleQuotedShell $dreamDbDatabase)).
    Replace("__DB_USER__", (Escape-SingleQuotedShell $dreamDbUsername)).
    Replace("__DB_PASSWORD__", (Escape-SingleQuotedShell $dreamDbPassword))

$remoteDumpPath = Invoke-DreamHostScript -HostName $dreamHostName -SshUser $dreamSshUser -SshPass $dreamSshPass -Script $dumpScript

if (-not $remoteDumpPath) {
    throw "DreamHost dump path was empty."
}

$plinkCandidate = "D:\programas\Putty\plink.exe"
$pscpCandidate = "D:\programas\Putty\pscp.exe"
$plinkPath = if (Test-Path $plinkCandidate) { $plinkCandidate } else { "plink.exe" }
$pscpPath = if (Test-Path $pscpCandidate) { $pscpCandidate } else { "pscp.exe" }
$localDumpPath = Join-Path ([System.IO.Path]::GetTempPath()) ("indoor-service-manager-{0}.sql.gz" -f ([guid]::NewGuid().ToString("N")))
$vmDumpPath = "/tmp/indoor-service-manager-import.sql.gz"

try {
    & $pscpPath -batch -pw $dreamSshPass "${dreamSshUser}@${dreamHostName}:$remoteDumpPath" $localDumpPath | Out-Null

    if ($LASTEXITCODE -ne 0) {
        throw "DreamHost dump download failed with exit code $LASTEXITCODE"
    }

    & $plinkPath -ssh $dreamHostName -l $dreamSshUser -pw $dreamSshPass "rm -f '$remoteDumpPath'" | Out-Null

    scp -i $vmKeyPath -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new $localDumpPath "ubuntu@${vmHostName}:$vmDumpPath"

    if ($LASTEXITCODE -ne 0) {
        throw "VM dump upload failed with exit code $LASTEXITCODE"
    }

    $adminCommand = ""

    if ($AdminPassword) {
        $adminEmailEscaped = Escape-SingleQuotedShell $AdminEmail
        $adminPasswordEscaped = Escape-SingleQuotedShell $AdminPassword
        $adminCommand = "php artisan app:ensure-admin-user '$adminEmailEscaped' --password='$adminPasswordEscaped' --name='Indoor Tech'"
    }

    $importScript = @"
set -euo pipefail
source /etc/indoor-service-manager/deploy.env
zcat '$vmDumpPath' | MYSQL_PWD="`$DB_PASSWORD" mysql --host="`$DB_HOST" --user="`$DB_USERNAME" "`$DB_DATABASE"
rm -f '$vmDumpPath'
if [ -d "`$APP_DIR" ] && [ -f "`$APP_DIR/artisan" ]; then
    cd "`$APP_DIR"
    php artisan migrate --force --no-interaction
    $adminCommand
    php artisan optimize:clear --no-interaction
    php artisan config:cache --no-interaction
    php artisan route:cache --no-interaction
    php artisan event:cache --no-interaction
    php artisan view:cache --no-interaction
fi
echo DB_IMPORT_OK
"@

    $localImportScript = Join-Path ([System.IO.Path]::GetTempPath()) ("oracle-import-{0}.sh" -f ([guid]::NewGuid().ToString("N")))
    $remoteImportScript = "/tmp/oracle-import-$([guid]::NewGuid().ToString("N")).sh"
    $utf8WithoutBom = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($localImportScript, $importScript, $utf8WithoutBom)

    try {
        scp -i $vmKeyPath -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new $localImportScript "ubuntu@${vmHostName}:$remoteImportScript"

        if ($LASTEXITCODE -ne 0) {
            throw "VM import script upload failed with exit code $LASTEXITCODE"
        }

        ssh -i $vmKeyPath -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new "ubuntu@$vmHostName" "chmod 700 $remoteImportScript && bash $remoteImportScript; status=`$?; rm -f $remoteImportScript; exit `$status"

        if ($LASTEXITCODE -ne 0) {
            throw "VM import failed with exit code $LASTEXITCODE"
        }
    } finally {
        if (Test-Path $localImportScript) {
            Remove-Item $localImportScript -Force
        }
    }
} finally {
    if (Test-Path $localDumpPath) {
        Remove-Item $localDumpPath -Force
    }
}

Write-Host "DB_MIGRATION_OK"
