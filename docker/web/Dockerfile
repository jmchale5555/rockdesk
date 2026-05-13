FROM php:8.3-apache

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libsqlite3-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        exif \
        gd \
        intl \
        mbstring \
        mysqli \
        pdo_mysql \
        pdo_sqlite \
    && if ! getent group 1000 >/dev/null; then groupadd --gid 1000 hostdev; fi \
    && if ! getent passwd 1000 >/dev/null; then useradd --uid 1000 --gid 1000 --no-create-home --shell /usr/sbin/nologin hostdev; fi \
    && a2enmod rewrite \
    && printf "ServerName localhost\n" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && sed -ri 's/^export APACHE_RUN_USER=.*/export APACHE_RUN_USER=${APACHE_RUN_USER:-www-data}/' /etc/apache2/envvars \
    && sed -ri 's/^export APACHE_RUN_GROUP=.*/export APACHE_RUN_GROUP=${APACHE_RUN_GROUP:-www-data}/' /etc/apache2/envvars \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY . /var/www/html

RUN composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader

WORKDIR /var/www/html
