param(
    [string] $VmConfigDir = "D:\ProgramData\oracle\pamis2",
    [string] $Domain = "itservice.gel5.com",
    [string] $Branch = "main",
    [string] $RepositoryUrl = "",
    [string] $AppRoot = "/var/www/indoor-service-manager",
    [string] $BareRepository = "/srv/git/indoor-service-manager.git",
    [string] $DbName = "indoor_service_manager",
    [string] $DbUser = "indoor_service_manager",
    [string] $DbPassword = "",
    [string] $AdminEmail = "contato@indoortech.com.br",
    [string] $AdminPassword = "",
    [string] $Gel5Endpoint = "",
    [string] $Gel5ApiKey = "",
    [string] $Gel5Root = "itservice",
    [string] $Gel5PublicUrl = ""
)

$ErrorActionPreference = "Stop"

function Get-RequiredFileContent {
    param([string] $Path)

    if (-not (Test-Path $Path)) {
        throw "Required file not found: $Path"
    }

    return (Get-Content -Raw $Path).Trim()
}

function Get-EnvValue {
    param(
        [string] $Path,
        [string] $Name
    )

    if (-not (Test-Path $Path)) {
        return ""
    }

    $line = Get-Content $Path | Where-Object { $_ -match "^$([regex]::Escape($Name))=" } | Select-Object -First 1

    if (-not $line) {
        return ""
    }

    return ($line -replace "^$([regex]::Escape($Name))=", "").Trim().Trim('"').Trim("'")
}

function New-Password {
    $bytes = [byte[]]::new(24)
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()

    try {
        $rng.GetBytes($bytes)
    } finally {
        $rng.Dispose()
    }

    return ([Convert]::ToBase64String($bytes) -replace '[+/=]', '').Substring(0, 24)
}

function Escape-SingleQuotedShell {
    param([string] $Value)

    return $Value -replace "'", "'\''"
}

function Invoke-RemoteScript {
    param(
        [string] $HostName,
        [string] $KeyPath,
        [string] $Script
    )

    $localScriptPath = Join-Path ([System.IO.Path]::GetTempPath()) ("oracle-provision-{0}.sh" -f ([guid]::NewGuid().ToString("N")))
    $remoteScriptPath = "/tmp/oracle-provision-$([guid]::NewGuid().ToString("N")).sh"

    try {
        $utf8WithoutBom = New-Object System.Text.UTF8Encoding($false)
        [System.IO.File]::WriteAllText($localScriptPath, $Script, $utf8WithoutBom)

        scp -i $KeyPath -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new $localScriptPath "ubuntu@${HostName}:$remoteScriptPath"

        if ($LASTEXITCODE -ne 0) {
            throw "Remote script upload failed with exit code $LASTEXITCODE"
        }

        ssh -i $KeyPath -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new "ubuntu@$HostName" "chmod 700 $remoteScriptPath && bash $remoteScriptPath; status=`$?; rm -f $remoteScriptPath; exit `$status"

        if ($LASTEXITCODE -ne 0) {
            throw "Remote script failed with exit code $LASTEXITCODE"
        }
    } finally {
        if (Test-Path $localScriptPath) {
            Remove-Item $localScriptPath -Force
        }
    }
}

$hostName = Get-RequiredFileContent (Join-Path $VmConfigDir "ip.txt")
$keyPath = Join-Path $VmConfigDir "ssh-key-2026-03-31.key"

if (-not (Test-Path $keyPath)) {
    throw "SSH key not found: $keyPath"
}

if (-not $RepositoryUrl) {
    $RepositoryUrl = (git config --get remote.origin.url).Trim()
}

if (-not $RepositoryUrl) {
    throw "RepositoryUrl was not provided and no git remote.origin.url was found."
}

if (-not $DbPassword) {
    $readExistingDbPasswordCommand = 'sudo test -f /etc/indoor-service-manager/deploy.env && sudo grep "^DB_PASSWORD=" /etc/indoor-service-manager/deploy.env | cut -d= -f2- | tr -d ''"'' || true'
    $existingDbPassword = ssh -i $keyPath -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new "ubuntu@$hostName" $readExistingDbPasswordCommand 2>$null

    if ($LASTEXITCODE -eq 0 -and $existingDbPassword) {
        $DbPassword = ($existingDbPassword | Select-Object -First 1).Trim().Trim('"').Trim("'")
    } else {
        $DbPassword = New-Password
    }
}

if (-not $Gel5Endpoint) {
    $Gel5Endpoint = Get-EnvValue ".env" "GEL5_FILES_ENDPOINT"
}

if (-not $Gel5ApiKey) {
    $Gel5ApiKey = Get-EnvValue ".env" "GEL5_FILES_API_KEY"
}

if (-not $Gel5Root) {
    $Gel5Root = Get-EnvValue ".env" "GEL5_FILES_ROOT"
}

if (-not $Gel5PublicUrl) {
    $Gel5PublicUrl = Get-EnvValue ".env" "GEL5_FILES_PUBLIC_URL"
}

$appKey = Get-EnvValue ".env" "APP_KEY"

if (-not $appKey) {
    throw "APP_KEY was not found in local .env. The VM must use the same key to decrypt production data."
}

if (-not $Gel5Endpoint) {
    $Gel5Endpoint = "https://files.gel5.com/api/index.php"
}

if (-not $Gel5Root) {
    $Gel5Root = "itservice"
}

if (-not $Gel5PublicUrl) {
    $Gel5PublicUrl = "https://$Domain/media"
}

$remoteScript = @'
set -euo pipefail

DOMAIN='__DOMAIN__'
BRANCH='__BRANCH__'
REPOSITORY_URL='__REPOSITORY_URL__'
APP_ROOT='__APP_ROOT__'
APP_DIR="$APP_ROOT/current"
BARE_REPOSITORY='__BARE_REPOSITORY__'
DB_NAME='__DB_NAME__'
DB_USER='__DB_USER__'
DB_PASSWORD='__DB_PASSWORD__'
APP_KEY_VALUE='__APP_KEY__'
ADMIN_EMAIL_VALUE='__ADMIN_EMAIL__'
ADMIN_PASSWORD_VALUE='__ADMIN_PASSWORD__'
GEL5_ENDPOINT_VALUE='__GEL5_ENDPOINT__'
GEL5_API_KEY_VALUE='__GEL5_API_KEY__'
GEL5_ROOT_VALUE='__GEL5_ROOT__'
GEL5_PUBLIC_URL_VALUE='__GEL5_PUBLIC_URL__'
SERVER_IP='__SERVER_IP__'

export DEBIAN_FRONTEND=noninteractive

sudo apt-get update
sudo apt-get install -y \
    certbot \
    curl \
    git \
    gzip \
    mysql-client \
    mysql-server \
    nginx \
    php-bcmath \
    php-cli \
    php-curl \
    php-fpm \
    php-gd \
    php-intl \
    php-mbstring \
    php-mysql \
    php-opcache \
    php-xml \
    php-zip \
    python3-certbot-nginx \
    rsync \
    unzip

PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
PHP_FPM_SERVICE="php${PHP_VERSION}-fpm"

if ! command -v composer >/dev/null 2>&1; then
    tmp_dir="$(mktemp -d)"
    cd "$tmp_dir"
    EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

    if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
        rm -rf "$tmp_dir"
        echo 'Composer installer checksum failed' >&2
        exit 1
    fi

    sudo php composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm -rf "$tmp_dir"
fi

sudo systemctl enable --now mysql nginx "$PHP_FPM_SERVICE"

sudo mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL

sudo mkdir -p "$APP_ROOT" "$(dirname "$BARE_REPOSITORY")" /etc/indoor-service-manager
sudo chown -R ubuntu:www-data "$APP_ROOT"
sudo chmod -R 2775 "$APP_ROOT"
sudo chown -R ubuntu:ubuntu "$(dirname "$BARE_REPOSITORY")"

if [ ! -d "$BARE_REPOSITORY" ]; then
    git init --bare "$BARE_REPOSITORY"
fi

cat > /tmp/indoor-deploy.env <<ENV
DOMAIN="$DOMAIN"
APP_ROOT="$APP_ROOT"
APP_DIR="$APP_DIR"
BARE_REPOSITORY="$BARE_REPOSITORY"
BRANCH="$BRANCH"
REPOSITORY_URL="$REPOSITORY_URL"
APP_NAME="Indoor Service Manager"
APP_ENV="production"
APP_KEY='$APP_KEY_VALUE'
APP_DEBUG="false"
APP_URL="https://$DOMAIN"
DB_CONNECTION="mysql"
DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_DATABASE="$DB_NAME"
DB_USERNAME="$DB_USER"
DB_PASSWORD='$DB_PASSWORD'
SESSION_DRIVER="file"
SESSION_LIFETIME="120"
SESSION_ENCRYPT="false"
SESSION_PATH="/"
SESSION_DOMAIN="null"
SESSION_SECURE_COOKIE="true"
BROADCAST_CONNECTION="log"
FILESYSTEM_DISK="local"
LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK="local"
QUEUE_CONNECTION="database"
CACHE_STORE="file"
LOG_CHANNEL="stack"
LOG_STACK="single"
LOG_LEVEL="warning"
GEL5_FILES_ENDPOINT="$GEL5_ENDPOINT_VALUE"
GEL5_FILES_API_KEY='$GEL5_API_KEY_VALUE'
GEL5_FILES_ROOT="$GEL5_ROOT_VALUE"
GEL5_FILES_PUBLIC_URL="$GEL5_PUBLIC_URL_VALUE"
MAIL_MAILER="log"
ADMIN_EMAIL="$ADMIN_EMAIL_VALUE"
ADMIN_PASSWORD='$ADMIN_PASSWORD_VALUE'
PHP_FPM_SERVICE="$PHP_FPM_SERVICE"
ENV

sudo mv /tmp/indoor-deploy.env /etc/indoor-service-manager/deploy.env
sudo chown root:ubuntu /etc/indoor-service-manager/deploy.env
sudo chmod 0640 /etc/indoor-service-manager/deploy.env

cat > /tmp/indoor-post-receive <<'HOOK'
#!/usr/bin/env bash
set -euo pipefail

source /etc/indoor-service-manager/deploy.env

while read -r oldrev newrev refname; do
    if [ "$refname" != "refs/heads/$BRANCH" ]; then
        continue
    fi

    mkdir -p "$APP_DIR"
    git --work-tree="$APP_DIR" --git-dir="$BARE_REPOSITORY" checkout -f "$BRANCH"

    cd "$APP_DIR"

    cat > .env <<ENV
APP_NAME="$APP_NAME"
APP_ENV="$APP_ENV"
APP_KEY='$APP_KEY'
APP_DEBUG="$APP_DEBUG"
APP_URL="$APP_URL"

APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
APP_FAKER_LOCALE=pt_BR

LOG_CHANNEL="$LOG_CHANNEL"
LOG_STACK="$LOG_STACK"
LOG_LEVEL="$LOG_LEVEL"

DB_CONNECTION="$DB_CONNECTION"
DB_HOST="$DB_HOST"
DB_PORT="$DB_PORT"
DB_DATABASE="$DB_DATABASE"
DB_USERNAME="$DB_USERNAME"
DB_PASSWORD='$DB_PASSWORD'

SESSION_DRIVER="$SESSION_DRIVER"
SESSION_LIFETIME="$SESSION_LIFETIME"
SESSION_ENCRYPT="$SESSION_ENCRYPT"
SESSION_PATH="$SESSION_PATH"
SESSION_DOMAIN=$SESSION_DOMAIN
SESSION_SECURE_COOKIE="$SESSION_SECURE_COOKIE"

BROADCAST_CONNECTION="$BROADCAST_CONNECTION"
FILESYSTEM_DISK="$FILESYSTEM_DISK"
LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK="$LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK"
QUEUE_CONNECTION="$QUEUE_CONNECTION"
CACHE_STORE="$CACHE_STORE"

GEL5_FILES_ENDPOINT="$GEL5_FILES_ENDPOINT"
GEL5_FILES_API_KEY='$GEL5_FILES_API_KEY'
GEL5_FILES_ROOT="$GEL5_FILES_ROOT"
GEL5_FILES_PUBLIC_URL="$GEL5_FILES_PUBLIC_URL"

MAIL_MAILER="$MAIL_MAILER"
ENV

    php artisan optimize:clear --no-interaction || true

    composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts
    php artisan package:discover --ansi --no-interaction

    php artisan migrate --force --no-interaction

    if [ -n "${ADMIN_PASSWORD:-}" ]; then
        php artisan app:ensure-admin-user "$ADMIN_EMAIL" --password="$ADMIN_PASSWORD" --name="Indoor Tech"
    fi

    php artisan storage:link --force --no-interaction || true
    php artisan optimize:clear --no-interaction
    php artisan config:cache --no-interaction
    php artisan route:cache --no-interaction
    php artisan event:cache --no-interaction
    php artisan view:cache --no-interaction

    mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache
    sudo chown -R ubuntu:www-data "$APP_DIR"
    sudo chmod -R ug+rw storage bootstrap/cache
    sudo find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type d -exec chmod 2775 {} \;
    sudo find "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" -type f -exec chmod 0664 {} \;

    sudo systemctl reload "$PHP_FPM_SERVICE"
    sudo systemctl reload nginx
done
HOOK

chmod 0755 /tmp/indoor-post-receive
mv /tmp/indoor-post-receive "$BARE_REPOSITORY/hooks/post-receive"

sudo tee "/etc/sudoers.d/indoor-service-manager-deploy" >/dev/null <<SUDOERS
ubuntu ALL=(root) NOPASSWD: /usr/bin/chown, /usr/bin/find, /usr/bin/systemctl reload nginx, /usr/bin/systemctl reload $PHP_FPM_SERVICE
SUDOERS
sudo chmod 0440 /etc/sudoers.d/indoor-service-manager-deploy

sudo tee "/etc/nginx/sites-available/indoor-service-manager" >/dev/null <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN $SERVER_IP;

    root $APP_DIR/public;
    index index.php;

    client_max_body_size 64M;
    server_tokens off;

    gzip on;
    gzip_comp_level 5;
    gzip_min_length 1024;
    gzip_types text/plain text/css application/json application/javascript application/xml image/svg+xml;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt { access_log off; log_not_found off; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

sudo ln -sfn /etc/nginx/sites-available/indoor-service-manager /etc/nginx/sites-enabled/indoor-service-manager
sudo rm -f /etc/nginx/sites-enabled/default

sudo tee "/etc/php/${PHP_VERSION}/fpm/conf.d/99-indoor-performance.ini" >/dev/null <<PHPINI
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=96
opcache.interned_strings_buffer=12
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
realpath_cache_size=4096K
realpath_cache_ttl=600
memory_limit=256M
upload_max_filesize=64M
post_max_size=64M
max_execution_time=120
PHPINI

sudo sed -i \
    -e 's/^pm = .*/pm = ondemand/' \
    -e 's/^pm.max_children = .*/pm.max_children = 8/' \
    -e 's/^pm.process_idle_timeout = .*/pm.process_idle_timeout = 10s/' \
    -e 's/^pm.max_requests = .*/pm.max_requests = 500/' \
    "/etc/php/${PHP_VERSION}/fpm/pool.d/www.conf"

sudo tee "/etc/mysql/mysql.conf.d/99-indoor-performance.cnf" >/dev/null <<MYSQLCNF
[mysqld]
innodb_buffer_pool_size=192M
innodb_log_file_size=64M
max_connections=40
table_open_cache=512
tmp_table_size=32M
max_heap_table_size=32M
MYSQLCNF

sudo tee /usr/local/sbin/indoor-issue-ssl >/dev/null <<'SSL'
#!/usr/bin/env bash
set -euo pipefail

source /etc/indoor-service-manager/deploy.env

certbot --nginx \
    --non-interactive \
    --agree-tos \
    --redirect \
    --email "$ADMIN_EMAIL" \
    -d "$DOMAIN"

systemctl enable --now certbot.timer
systemctl reload nginx
SSL

sudo chmod 0755 /usr/local/sbin/indoor-issue-ssl

if command -v ufw >/dev/null 2>&1; then
    sudo ufw allow OpenSSH >/dev/null || true
    sudo ufw allow 'Nginx Full' >/dev/null || true
fi

sudo nginx -t
sudo systemctl restart "$PHP_FPM_SERVICE"
sudo systemctl restart mysql
sudo systemctl reload nginx

echo "PROVISION_OK http://$SERVER_IP"
'@

$replacements = @{
    "__DOMAIN__" = Escape-SingleQuotedShell $Domain
    "__BRANCH__" = Escape-SingleQuotedShell $Branch
    "__REPOSITORY_URL__" = Escape-SingleQuotedShell $RepositoryUrl
    "__APP_ROOT__" = Escape-SingleQuotedShell $AppRoot
    "__BARE_REPOSITORY__" = Escape-SingleQuotedShell $BareRepository
    "__DB_NAME__" = Escape-SingleQuotedShell $DbName
    "__DB_USER__" = Escape-SingleQuotedShell $DbUser
    "__DB_PASSWORD__" = Escape-SingleQuotedShell $DbPassword
    "__APP_KEY__" = Escape-SingleQuotedShell $appKey
    "__ADMIN_EMAIL__" = Escape-SingleQuotedShell $AdminEmail
    "__ADMIN_PASSWORD__" = Escape-SingleQuotedShell $AdminPassword
    "__GEL5_ENDPOINT__" = Escape-SingleQuotedShell $Gel5Endpoint
    "__GEL5_API_KEY__" = Escape-SingleQuotedShell $Gel5ApiKey
    "__GEL5_ROOT__" = Escape-SingleQuotedShell $Gel5Root
    "__GEL5_PUBLIC_URL__" = Escape-SingleQuotedShell $Gel5PublicUrl
    "__SERVER_IP__" = Escape-SingleQuotedShell $hostName
}

foreach ($key in $replacements.Keys) {
    $remoteScript = $remoteScript.Replace($key, $replacements[$key])
}

Invoke-RemoteScript -HostName $hostName -KeyPath $keyPath -Script $remoteScript

git config "remote.oracle.url" "ubuntu@${hostName}:$BareRepository"
git config "remote.oracle.fetch" "+refs/heads/*:refs/remotes/oracle/*"
$keyPathForGit = $keyPath -replace '\\', '/'
git config core.sshCommand "ssh -i `"$keyPathForGit`" -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new"

Write-Host "PROVISION_OK"
Write-Host "Deploy with: git push oracle $Branch"
