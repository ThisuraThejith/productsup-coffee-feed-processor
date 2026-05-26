FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    sqlite3 \
    libzip-dev \
    libsqlite3-dev \
    && docker-php-ext-install zip pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock symfony.lock* ./

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --no-scripts \
    --optimize-autoloader

COPY . .

RUN composer dump-autoload --optimize

CMD ["php", "bin/console"]
