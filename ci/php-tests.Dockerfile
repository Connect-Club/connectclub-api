FROM php:7.4-fpm-alpine3.13 as composer

RUN echo http://mirror.yandex.ru/mirrors/alpine/v3.13/main > /etc/apk/repositories; \
  	echo http://mirror.yandex.ru/mirrors/alpine/v3.13/community >> /etc/apk/repositories

RUN apk update \
    && apk add  --no-cache git postgresql-dev rabbitmq-c rabbitmq-c-dev curl libzip-dev libmcrypt libmcrypt-dev openssh-client bash icu-dev gmp-dev \
    libxml2-dev freetype-dev libpng-dev libjpeg-turbo-dev libwebp-dev zlib-dev libxpm-dev g++ make autoconf \
    && docker-php-source extract \
    && docker-php-source delete \
    && docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_pgsql soap intl zip gd exif bcmath sockets gmp \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --version=1.10.16 --filename=composer \
    && rm -rf /tmp/* \
    && pecl install amqp redis && docker-php-ext-enable amqp redis

RUN \
 curl -L https://download.newrelic.com/php_agent/archive/9.9.0.260/newrelic-php5-9.9.0.260-linux-musl.tar.gz | tar -C /tmp -zx && \
   NR_INSTALL_USE_CP_NOT_LN=1 NR_INSTALL_SILENT=1 /tmp/newrelic-php5-*/newrelic-install install

CMD ["php-fpm", "-F"]
WORKDIR /var/www

EXPOSE 9000

FROM composer as app
ADD composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --no-suggest --optimize-autoloader --classmap-authoritative --no-scripts --no-cache
ADD ci/php.ini /usr/local/etc/php/php.ini
ADD ci/php_env .env
ADD ci/www.conf /usr/local/etc/php-fpm.d/www.conf
ADD . .
RUN SENTRY_DSN="" composer install --no-interaction --no-progress --no-suggest --optimize-autoloader --classmap-authoritative --no-cache
RUN composer dump-autoload --optimize --classmap-authoritative
RUN chown -R www-data:www-data var/*
