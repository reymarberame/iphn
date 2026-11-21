#!/bin/sh
set -eu

PORT="${PORT:-8080}"

# Railway injects PORT and routes public traffic to it. A single service hosts
# Apache and Reverb, so Reverb stays on an internal port behind the proxy.
sed -i "s/^Listen 8080$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:8080>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

cd /var/www/html

php artisan storage:link >/dev/null 2>&1 || true

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

chown -R www-data:www-data storage bootstrap/cache

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/bhn.conf
