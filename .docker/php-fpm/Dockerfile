FROM php:7.4-fpm

RUN apt-get update && apt-get install -y zip

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions http pdo pdo_mysql

WORKDIR /var/www/html

RUN groupadd -g 1000 www \
    && useradd -u 1000 -ms /bin/bash -g www www \
    && chown -R www:www /var/www/html

USER www

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

EXPOSE 9000

CMD ["php-fpm"]