FROM dunglas/frankenphp:1-php8.4

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip zip libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions pdo_sqlite mbstring xml curl bcmath pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
