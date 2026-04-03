FROM php:8.3-cli-alpine

RUN apk add --no-cache \
    git \
    unzip \
    sqlite \
    sqlite-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    linux-headers \
    $PHPIZE_DEPS

RUN docker-php-ext-install \
    bcmath \
    mbstring \
    pdo \
    pdo_sqlite \
    pcntl \
    intl \
    zip

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN if [ -f composer.json ]; then composer install --no-interaction --prefer-dist; fi

EXPOSE 8000

CMD ["sh", "-lc", "mkdir -p database && touch database/database.sqlite && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000"]
