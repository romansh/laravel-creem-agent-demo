FROM dunglas/frankenphp:1-php8.4

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip zip libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions pdo_sqlite mbstring xml curl bcmath pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Allow Git operations when mounted repo is owned by a different UID (common in CI/containers)
# This sets a safe.directory for the container user so composer/git commands won't fail.
RUN git config --global --add safe.directory /app
