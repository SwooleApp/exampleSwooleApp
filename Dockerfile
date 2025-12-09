FROM phpswoole/swoole:php8.4


WORKDIR /var/www

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY . .


RUN composer install --no-dev --no-interaction --optimize-autoloader --ignore-platform-reqs


RUN rm -f /usr/local/bin/composer
