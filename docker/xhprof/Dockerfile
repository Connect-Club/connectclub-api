FROM php

ENV APP_DIR /var/www/html
ENV APPLICATION_ENV development

RUN pecl install xhprof && docker-php-ext-enable xhprof
RUN mkdir -p $APP_DIR && apt update && apt install graphviz git -y

WORKDIR $APP_DIR
EXPOSE 80

RUN git clone https://github.com/longxinH/xhprof.git .
RUN echo 'xhprof.output_dir=/tmp/xhprof/' >> /usr/local/etc/php/php.ini
CMD ["php", "-S", "0.0.0.0:80", "-t", "/var/www/html/xhprof_html"]