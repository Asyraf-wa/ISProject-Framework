# PHP-FPM image for local development of isproject/framework.
# Laravel 13 requires PHP 8.3+; 8.4 is used here.
FROM php:8.4-fpm

ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        zip \
        curl \
        libzip-dev \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-isproject.ini

# Run as a user matching the host so files written inside the container stay
# editable from Windows/WSL. Override with UID/GID in .env if yours differ.
RUN groupadd -f -g "${GID}" dev \
    && useradd -u "${UID}" -g "${GID}" -m -s /bin/bash dev \
    && mkdir -p /home/dev/.composer/cache \
    && chown -R "${UID}:${GID}" /home/dev

WORKDIR /var/www

USER dev

EXPOSE 9000

CMD ["php-fpm"]
