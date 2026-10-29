# syntax=docker/dockerfile:1

ARG NODE_VERSION=24.22.2
ARG PHP_VERSION=8.4.27
ARG APP_USER=www-data
ARG DB_DRIVER=pgsql

# Compila los assets en la versión de node indicada
FROM node:${NODE_VERSION}-alpine AS frontend

WORKDIR /src

COPY package*.json ./
RUN npm ci

COPY . .
RUN npm run build


# Define la versión de PHP y el usuario del contenedor
FROM php:${PHP_VERSION}-fpm-alpine

WORKDIR /var/www/html

ARG APP_USER
ARG DB_DRIVER

# Dependencias del sistema
RUN apk add --no-cache \
    bash \
    curl \
    git \
    libpng-dev \
    jpeg-dev \
    libwebp-dev \
    icu-dev \
    libxml2-dev \
    oniguruma-dev \
    libzip-dev \
    zip \
    unzip \
    && if [ "${DB_DRIVER}" = "pgsql" ]; then apk add --no-cache postgresql-dev; fi \
    && if [ "${DB_DRIVER}" = "mysql" ]; then apk add --no-cache mysql-dev; fi \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_${DB_DRIVER} gd intl zip opcache \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Dependencias de composer
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts --no-autoloader --no-dev

# Código de la app y assets de Vite compilados
COPY . .
COPY --from=frontend /src/public /var/www/html/public

# Autoload optimizado
RUN composer dump-autoload --optimize --no-dev \
    && php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear \
    && chown -R ${APP_USER}:${APP_USER} /var/www/html/storage /var/www/html/bootstrap/cache

# Puerto para el proxy interno
EXPOSE 9000

USER ${APP_USER}

CMD ["php-fpm", "-F"]
