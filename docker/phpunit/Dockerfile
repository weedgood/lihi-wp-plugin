FROM php:8.2-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
    && docker-php-ext-install zip mysqli \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN useradd -m -u 1000 phpunit

USER phpunit

WORKDIR /app
