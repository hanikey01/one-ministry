FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --optimize-autoloader

FROM node:25-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.5.12-fpm-alpine3.24
ARG INSTALL_REDIS_EXTENSION=true
RUN apk add --no-cache libpq libpq-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-install pdo_pgsql pdo_mysql \
    && if [ "$INSTALL_REDIS_EXTENSION" = "true" ]; then pecl install redis && docker-php-ext-enable redis; fi \
    && apk del $PHPIZE_DEPS linux-headers libpq-dev
WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build /opt/ministry-build
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && rm -f bootstrap/cache/*.php \
    && chown -R www-data:www-data storage bootstrap/cache \
    && php artisan package:discover --ansi
EXPOSE 9000
CMD ["php-fpm"]
