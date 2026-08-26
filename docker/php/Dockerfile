FROM php:8.4-cli-alpine

RUN apk add --no-cache bash curl curl-dev git icu-dev libzip-dev mysql-client oniguruma-dev unzip \
    && docker-php-ext-install bcmath curl intl mbstring pcntl pdo_mysql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8080
