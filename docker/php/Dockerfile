# Modrix Commerce PHP-FPM image.
#
# Targets:
#   runtime  PHP-FPM with the required extensions only (production-style).
#   dev      runtime + Node.js/npm so `npm run build` (which invokes
#            `php artisan wayfinder:generate`) can run inside the container.

FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache icu-libs libzip libpng libjpeg-turbo freetype \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath intl pdo_mysql zip gd opcache pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-ecomodular.ini

WORKDIR /var/www/html

# PHP-FPM workers run as www-data. Only storage/ and bootstrap/cache/ need to be writable.
CMD ["php-fpm"]

FROM runtime AS dev
RUN apk add --no-cache nodejs npm
