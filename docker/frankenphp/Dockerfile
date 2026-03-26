FROM dunglas/frankenphp:1-php8.4

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip zip libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions pdo_sqlite mbstring xml curl bcmath pcntl pcov

# Enable pcov and configure default directory (will be overridden at runtime)
RUN { \
    echo "extension=pcov.so" > /usr/local/etc/php/conf.d/20-pcov.ini; \
    echo "pcov.enabled=1" >> /usr/local/etc/php/conf.d/20-pcov.ini; \
    echo "pcov.directory=/home/www/laravel-creem-agent" >> /usr/local/etc/php/conf.d/20-pcov.ini; \
}

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
