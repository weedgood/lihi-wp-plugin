FROM mcr.microsoft.com/devcontainers/php:3-8.2-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
    && docker-php-ext-install zip mysqli \
    && rm -rf /var/lib/apt/lists/*