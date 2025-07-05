FROM php:7.4-fpm

RUN apt-get update && apt-get install -y \
    curl \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql

WORKDIR /var/www/html

RUN groupadd -g 1000 www
RUN useradd -u 1000 -ms /bin/bash -g www www
RUN chown -R www:www /var/www/html
USER www

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

EXPOSE 80/tcp
EXPOSE 9000

CMD ["php-fpm"]