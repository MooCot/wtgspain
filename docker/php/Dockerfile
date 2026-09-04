FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        gosu \
    && docker-php-ext-install pdo pdo_mysql bcmath zip gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint-wtgspain.sh
RUN chmod +x /usr/local/bin/docker-entrypoint-wtgspain.sh

WORKDIR /var/www

ENTRYPOINT ["docker-entrypoint-wtgspain.sh"]
CMD ["php-fpm"]
