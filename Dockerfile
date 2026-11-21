# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Front-end assets (Vite + React + Tailwind)
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

RUN npm run build

# ---------------------------------------------------------------------------
# Application image (Apache + PHP 8.4 + Reverb + scheduler)
# ---------------------------------------------------------------------------
FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev \
        libzip-dev \
        libonig-dev \
        supervisor \
        unzip \
    && docker-php-ext-install pdo_pgsql pgsql mbstring zip bcmath opcache pcntl \
    && a2enmod rewrite headers proxy proxy_http proxy_wstunnel \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies first so Docker can cache this layer.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --no-scripts \
        --no-autoloader

# Application source and compiled assets.
COPY . .
COPY --from=assets /app/public/build ./public/build

RUN mkdir -p \
        storage/app/private \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && composer dump-autoload --optimize --no-dev --no-interaction \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/ports.conf /etc/apache2/ports.conf
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/bhn.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# PHP settings suitable for 10 MB referral uploads plus headroom.
RUN { \
        echo 'upload_max_filesize = 12M'; \
        echo 'post_max_size = 13M'; \
        echo 'memory_limit = 256M'; \
        echo 'opcache.enable = 1'; \
        echo 'opcache.validate_timestamps = 0'; \
        echo 'expose_php = Off'; \
    } > /usr/local/etc/php/conf.d/bhn.ini

# libpq looks for client certificates under $HOME/.postgresql. Railway's build
# image leaves a root-owned directory there, which www-data cannot read. Give
# every runtime process a writable home so SSL connections do not try to open it.
ENV HOME=/var/www/html

EXPOSE 8080 8081

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
