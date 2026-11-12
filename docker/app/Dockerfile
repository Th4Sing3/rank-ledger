FROM php:8.3-cli-alpine AS php-base

RUN apk add --no-cache \
        libxml2 \
        libxml2-dev \
        libzip \
        libzip-dev \
        oniguruma \
        oniguruma-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" bcmath mbstring opcache pcntl pdo_mysql xml zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

FROM php-base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --no-scripts \
        --optimize-autoloader

COPY . .
RUN composer dump-autoload --optimize

FROM php-base AS runtime

WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /app /var/www/html
COPY --from=vendor /app/docker/app/entrypoint.sh /usr/local/bin/rank-ledger-entrypoint

RUN chmod 0555 /usr/local/bin/rank-ledger-entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 8000

ENTRYPOINT ["/usr/local/bin/rank-ledger-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
