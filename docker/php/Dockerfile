FROM php:8.3-fpm-alpine

# Zależności systemowe + biblioteki dla rozszerzeń PHP
RUN apk add --no-cache \
        bash \
        git \
        unzip \
        icu-dev \
        libzip-dev \
        postgresql-dev \
        oniguruma-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        intl \
        zip \
        gd \
        bcmath \
        opcache \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Konfiguracja PHP
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Entrypoint – instalacja zależności, migracje, seed przy starcie
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

# Kod aplikacji jest montowany (bind) w docker-compose; vendor instaluje entrypoint.
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
