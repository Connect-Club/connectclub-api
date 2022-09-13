FROM php:7.4-fpm-alpine3.13

RUN apk update \
    && apk add  --no-cache git rabbitmq-c rabbitmq-c-dev postgresql-dev curl libzip-dev libmcrypt-dev openssh-client bash icu-dev \
    libxml2-dev freetype-dev libpng-dev libjpeg-turbo-dev libwebp-dev zlib-dev libxpm-dev g++ make autoconf gmp-dev \
    && docker-php-source extract \
    && pecl install xdebug-2.9.8 xhprof amqp redis gmp \
    && docker-php-ext-enable xdebug xhprof amqp redis \
    && docker-php-source delete \
    && docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install opcache pdo_pgsql soap intl zip gd exif bcmath sockets gmp \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --version=1.10.21 --filename=composer \
    && rm -rf /tmp/* \
    && mkdir /tmp/xhprof && chown www-data /tmp/xhprof && chmod 777 -R /tmp/xhprof

ADD ./php.ini /usr/local/etc/php/php.ini
CMD ["php-fpm", "-F"]
WORKDIR /var/www

EXPOSE 9000
