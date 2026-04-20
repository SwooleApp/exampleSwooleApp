FROM phpswoole/swoole:php8.4

WORKDIR /var/www

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY composer.json ./

RUN composer config allow-plugins.yurunsoft/composer-include-files true && \
    composer config allow-plugins.yurunsoft/guzzle-swoole true

COPY swooleApp_1/ swooleApp_1/
COPY vendor/ vendor/
COPY src/ src/
COPY config.json ./
COPY server.php ./
COPY test_ok.php ./

RUN composer dump-autoload --optimize

EXPOSE 9501

CMD ["php", "server.php"]