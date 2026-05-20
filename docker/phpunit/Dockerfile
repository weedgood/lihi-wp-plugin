ARG PHP_IMAGE=php:7.4-cli-bullseye
FROM ${PHP_IMAGE}

ENV XDEBUG_MODE=off

RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        gettext \
        libzip-dev \
        make \
        unzip \
    && docker-php-ext-install zip mysqli \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php \
    && php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm -f /tmp/composer-setup.php \
    && groupadd --gid 1000 vscode \
    && useradd --uid 1000 --gid 1000 --create-home --shell /bin/bash vscode \
    && mkdir -p /app/code \
    && chown -R vscode:vscode /app \
    && rm -rf /var/lib/apt/lists/*

USER vscode

WORKDIR /app/code
