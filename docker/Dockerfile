# ── Stage 0 : dépendances PHP (Ziggy requis par Vite) ────────────────────────
FROM php:8.4-cli-bookworm AS composer

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# ── Stage 1 : build des assets Vue/Vite (pnpm) ───────────────────────────────
FROM node:24-bookworm-slim AS frontend

# Node 24 requis : package.json fixe engines.node >=24 et .npmrc a engine-strict=true.
RUN corepack enable

WORKDIR /app

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml .npmrc ./
RUN pnpm install --frozen-lockfile

COPY vite.config.js postcss.config.js tailwind.config.js jsconfig.json ./
COPY resources ./resources
COPY public ./public
COPY --from=composer /app/vendor/tightenco/ziggy ./vendor/tightenco/ziggy

ARG VITE_APP_NAME="PixelCraft Studio"
ARG VITE_REVERB_APP_KEY=""
ARG VITE_REVERB_HOST="localhost"
ARG VITE_REVERB_PORT="8080"
ARG VITE_REVERB_SCHEME="http"

ENV VITE_APP_NAME=$VITE_APP_NAME \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

RUN pnpm build

# ── Stage 2 : application PHP ────────────────────────────────────────────────
FROM php:8.4-fpm-bookworm AS app

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    gosu \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libicu-dev \
    libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j1 \
        pdo_mysql \
        mbstring \
        zip \
        bcmath \
        intl \
        gd \
        pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
COPY --from=composer /app/vendor ./vendor

COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F"]

# ── Stage 3 : Nginx (même filesystem que l'app) ──────────────────────────────
FROM app AS nginx

USER root

RUN apt-get update && apt-get install -y --no-install-recommends nginx \
    && rm -rf /var/lib/apt/lists/* \
    && rm /etc/nginx/sites-enabled/default

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

ENTRYPOINT []
CMD ["nginx", "-g", "daemon off;"]
