#!/bin/sh
set -e

cd /var/www/html

# Storage może być wolumenem (persystencja załączników/logów) – odtwarzamy strukturę.
mkdir -p storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs \
         storage/app/public storage/app/private storage/app/purifier \
         bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Migracje wykonuje osobny, jednorazowy serwis "migrate" w docker-compose.prod.yml.
# Tu (php-fpm) przygotowujemy tylko link do storage i cache produkcyjny.
if [ "$1" = "php-fpm" ]; then
    php artisan storage:link 2>/dev/null || true
    php artisan config:cache || true
    php artisan view:cache || true
    php artisan route:cache || true
fi

exec "$@"
