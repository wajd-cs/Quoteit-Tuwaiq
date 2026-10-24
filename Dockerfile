# QuoteIt image for Render (free plan). Builds the CSS/JS first, then the PHP app.

FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

FROM php:8.4-cli-bookworm
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev libicu-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql intl zip opcache \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=assets /app/public ./public
RUN composer dump-autoload --optimize --no-dev \
    && rm -f bootstrap/cache/*.php \
    && sed -i 's/\r$//' docker/start.sh

ENV PHP_CLI_SERVER_WORKERS=4
EXPOSE 10000
CMD ["sh", "docker/start.sh"]
