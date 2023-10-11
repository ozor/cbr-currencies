FROM php:8.2-fpm

COPY --from=composer/composer:latest-bin /composer /usr/local/bin/composer

RUN apt-get update --fix-missing && \
    apt-get install -y \
    git unzip \
    libpq-dev \
    libc-client-dev \
    libssh-dev \
    librabbitmq-dev \
    libmemcached-dev \
    libkrb5-dev && rm -r /var/lib/apt/lists/* \
    && pecl install xdebug-3.2.0 \
    && docker-php-ext-enable xdebug \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install imap \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && docker-php-ext-install bcmath

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]