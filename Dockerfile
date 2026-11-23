FROM php:8.4-cli-bookworm

ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libicu-dev \
        libonig-dev \
        libzip-dev \
        unzip \
        zip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

RUN if ! getent group "${GID}" >/dev/null; then groupadd --gid "${GID}" laravel; fi \
    && if ! getent passwd "${UID}" >/dev/null; then useradd --uid "${UID}" --gid "${GID}" --shell /bin/bash --create-home laravel; fi

WORKDIR /var/www/html

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod 755 /usr/local/bin/entrypoint.sh

USER ${UID}:${GID}

EXPOSE 8000

ENTRYPOINT ["entrypoint.sh"]
# --no-reload keeps Compose variables such as DB_HOST. Without it, serve
# drops them when .env exists and the web process uses 127.0.0.1.
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
