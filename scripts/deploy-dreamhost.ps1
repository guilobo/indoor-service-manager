param(
    [string] $ConfigPath = ".serverconfig",
    [string] $Branch = "main",
    [string] $RepositoryUrl = "",
    [string] $AdminEmail = "contato@indoortech.com.br",
    [string] $AdminPassword = "",
    [string] $Gel5ApiKey = "",
    [string] $Gel5Endpoint = "https://files.gel5.com/api/index.php",
    [string] $Gel5Root = "itservice"
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

function Escape-RemoteSingleQuoted {
    param([string] $Value)

    return $Value -replace "'", "'\''"
}

function Invoke-Remote {
    param(
        [string] $HostName,
        [string] $SshUser,
        [string] $SshPass,
        [string] $Command
    )

    & $script:PlinkPath -ssh $HostName -l $SshUser -pw $SshPass $Command

    if ($LASTEXITCODE -ne 0) {
        throw "Remote command failed with exit code $LASTEXITCODE"
    }
}

$pairs = Read-KeyValueConfig $ConfigPath
$domain = Get-ConfigValue $pairs @("domain", "app_domain")
$hostName = Get-ConfigValue $pairs @("host", "ssh_host")
$sshUser = Get-ConfigValue $pairs @("ssh_user", "user")
$sshPass = Get-ConfigValue $pairs @("ssh_pass", "pass")
$dbUsername = Get-ConfigValue $pairs @("db_user", "mysql_user", "user") 1
$dbPassword = Get-ConfigValue $pairs @("db_pass", "mysql_pass", "pass") 1
$dbDatabase = Get-ConfigValue $pairs @("db", "database", "db_database")
$dbHost = Get-ConfigValue $pairs @("db_host", "mysql_host", "server")

if (-not $domain -or -not $hostName -or -not $sshUser -or -not $sshPass -or -not $dbUsername -or -not $dbPassword -or -not $dbDatabase -or -not $dbHost) {
    throw "Missing required values in $ConfigPath"
}

if (-not $RepositoryUrl) {
    $RepositoryUrl = (git config --get remote.origin.url).Trim()
}

if (-not $RepositoryUrl) {
    throw "RepositoryUrl was not provided and no git remote.origin.url was found."
}

if (-not $AdminPassword) {
    $securePassword = Read-Host "Admin password" -AsSecureString
    $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)

    try {
        $AdminPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
    } finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    }
}

if (-not $Gel5ApiKey -and (Test-Path ".env")) {
    $Gel5ApiKey = (Get-Content ".env" | Where-Object { $_ -match '^GEL5_FILES_API_KEY=' } | Select-Object -First 1) -replace '^GEL5_FILES_API_KEY=', ''
    $Gel5ApiKey = $Gel5ApiKey.Trim('"').Trim("'")
}

if (-not $Gel5ApiKey) {
    throw "GEL5 API key was not provided and GEL5_FILES_API_KEY was not found in .env."
}

$plinkCandidate = "D:\programas\Putty\plink.exe"
$script:PlinkPath = if (Test-Path $plinkCandidate) { $plinkCandidate } else { "plink.exe" }

$appUrl = "https://$domain"
$appDir = "/home/$sshUser/itservice-app"
$webRoot = "/home/$sshUser/$domain"
$repoEscaped = Escape-RemoteSingleQuoted $RepositoryUrl
$branchEscaped = Escape-RemoteSingleQuoted $Branch
$appUrlEscaped = Escape-RemoteSingleQuoted $appUrl
$dbHostEscaped = Escape-RemoteSingleQuoted $dbHost
$dbDatabaseEscaped = Escape-RemoteSingleQuoted $dbDatabase
$dbUsernameEscaped = Escape-RemoteSingleQuoted $dbUsername
$dbPasswordEscaped = Escape-RemoteSingleQuoted $dbPassword
$gel5EndpointEscaped = Escape-RemoteSingleQuoted $Gel5Endpoint
$gel5RootEscaped = Escape-RemoteSingleQuoted $Gel5Root
$gel5ApiKeyEscaped = Escape-RemoteSingleQuoted $Gel5ApiKey
$adminEmailEscaped = Escape-RemoteSingleQuoted $AdminEmail
$adminPasswordEscaped = Escape-RemoteSingleQuoted $AdminPassword

$remoteScript = @"
set -euo pipefail

APP_DIR='$appDir'
WEB_ROOT='$webRoot'
REPOSITORY_URL='$repoEscaped'
BRANCH='$branchEscaped'
APP_URL_VALUE='$appUrlEscaped'
DB_HOST_VALUE='$dbHostEscaped'
DB_DATABASE_VALUE='$dbDatabaseEscaped'
DB_USERNAME_VALUE='$dbUsernameEscaped'
DB_PASSWORD_VALUE='$dbPasswordEscaped'
GEL5_ENDPOINT_VALUE='$gel5EndpointEscaped'
GEL5_ROOT_VALUE='$gel5RootEscaped'
GEL5_API_KEY_VALUE='$gel5ApiKeyEscaped'
ADMIN_EMAIL_VALUE='$adminEmailEscaped'
ADMIN_PASSWORD_VALUE='$adminPasswordEscaped'

mkdir -p "`$APP_DIR" "`$WEB_ROOT"

if [ ! -d "`$APP_DIR/.git" ]; then
    if [ -n "`$(find "`$APP_DIR" -mindepth 1 -maxdepth 1 2>/dev/null | head -n 1)" ]; then
        backup_dir="`$APP_DIR.backup.`$(date +%Y%m%d%H%M%S)"
        mv "`$APP_DIR" "`$backup_dir"
        mkdir -p "`$APP_DIR"
    fi

    git clone --branch "`$BRANCH" "`$REPOSITORY_URL" "`$APP_DIR"
else
    git -C "`$APP_DIR" fetch origin "`$BRANCH"
    git -C "`$APP_DIR" reset --hard "origin/`$BRANCH"
fi

if [ ! -f "`$HOME/composer.phar" ]; then
    EXPECTED_CHECKSUM="`$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_CHECKSUM="`$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

    if [ "`$EXPECTED_CHECKSUM" != "`$ACTUAL_CHECKSUM" ]; then
        rm composer-setup.php
        echo 'Composer installer checksum failed' >&2
        exit 1
    fi

    php composer-setup.php --quiet --install-dir="`$HOME" --filename=composer.phar
    rm composer-setup.php
fi

cd "`$APP_DIR"

EXISTING_APP_KEY=""
if [ -f .env ]; then
    EXISTING_APP_KEY="`$(grep -E '^APP_KEY=' .env | head -n 1 | cut -d= -f2- || true)"
fi

cat > .env <<ENV
APP_NAME="Indoor Service Manager"
APP_ENV=production
APP_KEY=`$EXISTING_APP_KEY
APP_DEBUG=false
APP_URL=`$APP_URL_VALUE

APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
APP_FAKER_LOCALE=pt_BR

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=`$DB_HOST_VALUE
DB_PORT=3306
DB_DATABASE=`$DB_DATABASE_VALUE
DB_USERNAME=`$DB_USERNAME_VALUE
DB_PASSWORD=`$DB_PASSWORD_VALUE

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

GEL5_FILES_ENDPOINT=`$GEL5_ENDPOINT_VALUE
GEL5_FILES_API_KEY=`$GEL5_API_KEY_VALUE
GEL5_FILES_ROOT=`$GEL5_ROOT_VALUE
GEL5_FILES_PUBLIC_URL="`$APP_URL_VALUE/media"

MAIL_MAILER=log
ENV

php "`$HOME/composer.phar" install --no-dev --prefer-dist --no-interaction --optimize-autoloader

if [ -z "`$EXISTING_APP_KEY" ]; then
    php artisan key:generate --force --no-interaction
fi

php artisan migrate --force --no-interaction
php artisan app:ensure-admin-user "`$ADMIN_EMAIL_VALUE" --password="`$ADMIN_PASSWORD_VALUE" --name="Indoor Tech"
php artisan storage:link --force --no-interaction || true
php artisan optimize:clear --no-interaction
php artisan config:cache --no-interaction
php artisan view:cache --no-interaction

chmod -R ug+rw storage bootstrap/cache

rsync -a --delete --exclude='.well-known' --exclude='.dh-diag' "`$APP_DIR/public/" "`$WEB_ROOT/"

cat > "`$WEB_ROOT/index.php" <<'PHP'
<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists('$appDir/storage/framework/maintenance.php')) {
    require '$appDir/storage/framework/maintenance.php';
}

require '$appDir/vendor/autoload.php';

/** @var Application `$app */
`$app = require_once '$appDir/bootstrap/app.php';

`$app->handleRequest(Request::capture());
PHP

echo "DEPLOY_OK `$APP_URL_VALUE"
"@

Write-Host "Deploying $RepositoryUrl#$Branch to $appUrl ..."
Invoke-Remote -HostName $hostName -SshUser $sshUser -SshPass $sshPass -Command $remoteScript
