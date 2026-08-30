FROM php:8.3-fpm

RUN apt-get update \
    && apt-get install -y git curl unzip mariadb-client libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY .docker/entrypoint.sh /usr/local/bin/discador-entrypoint
COPY .docker/app/php.ini /usr/local/etc/php/conf.d/discador.ini
COPY .docker/app/www.conf /usr/local/etc/php-fpm.d/zz-discador.conf
RUN chmod +x /usr/local/bin/discador-entrypoint

ENTRYPOINT ["discador-entrypoint"]
CMD ["php-fpm"]
