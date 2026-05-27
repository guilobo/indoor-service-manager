FROM php:8.3-fpm-alpine AS app-base

ARG APP_DIR=/var/www/html

WORKDIR ${APP_DIR}

RUN apk add --no-cache \
        bash \
        curl \
        git \
        icu-data-full \
        icu-libs \
        nginx \
        supervisor \
        unzip \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        libxml2-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        ftp \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        sockets \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --prefer-dist \
        --no-interaction \
        --optimize-autoloader \
        --no-scripts

COPY . ${APP_DIR}

FROM node:22-alpine AS frontend

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
COPY --from=app-base /var/www/html/vendor ./vendor

RUN npm run build

FROM app-base

COPY --from=frontend /app/public/build ${APP_DIR}/public/build
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/php/zz-custom.ini /usr/local/etc/php/conf.d/zz-custom.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/start.sh /start.sh

RUN chmod +x /start.sh \
    && rm -rf /root/.composer/cache \
    && mkdir -p \
        /run/nginx \
        /var/log/supervisor \
        ${APP_DIR}/storage/framework/cache/data \
        ${APP_DIR}/storage/framework/sessions \
        ${APP_DIR}/storage/framework/views \
        ${APP_DIR}/bootstrap/cache \
    && chown -R www-data:www-data ${APP_DIR}/storage ${APP_DIR}/bootstrap/cache

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --retries=3 --start-period=30s \
    CMD curl -fsS http://127.0.0.1/up || exit 1

ENTRYPOINT ["/start.sh"]
