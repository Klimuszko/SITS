#!/bin/sh
set -e

cd /var/www/html

# Tylko główny kontener aplikacji (php-fpm) przygotowuje środowisko.
# Worker i scheduler mają własne komendy (czekają na vendor/).
if [ "$1" = "php-fpm" ]; then
    echo "[entrypoint] Przygotowanie aplikacji..."

    # 1. Zależności Composer (zapisywane do zamontowanego katalogu – persystują na hoście).
    if [ ! -f vendor/autoload.php ]; then
        if [ -f composer.lock ]; then
            echo "[entrypoint] composer install..."
            composer install --no-interaction --prefer-dist --optimize-autoloader
        else
            echo "[entrypoint] Brak composer.lock – composer update..."
            composer update --no-interaction --prefer-dist --optimize-autoloader
        fi
    fi

    # 2. Katalogi zapisywalne + uprawnienia.
    mkdir -p storage/framework/cache/data storage/framework/sessions \
             storage/framework/views storage/logs \
             storage/app/public storage/app/private storage/app/purifier \
             bootstrap/cache
    chown -R www-data:www-data storage bootstrap/cache || true
    chmod -R 775 storage bootstrap/cache || true

    # 3. Klucz aplikacji (generowany tylko, jeśli pusty).
    if ! grep -q "^APP_KEY=base64" .env 2>/dev/null; then
        php artisan key:generate --force || true
    fi

    # 4. Czyszczenie cache konfiguracji (środowisko dev – zmiany .env mają działać).
    php artisan optimize:clear || true

    # 5. Migracje + seed (idempotentne).
    echo "[entrypoint] Migracje bazy danych..."
    php artisan migrate --force

    echo "[entrypoint] Seedowanie danych..."
    php artisan db:seed --force || true

    # 6. Link do publicznego storage.
    php artisan storage:link || true

    echo "[entrypoint] Aplikacja gotowa: http://localhost:7564"
fi

exec "$@"
