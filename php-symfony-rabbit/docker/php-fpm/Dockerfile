FROM php:8.5-fpm

COPY --from=composer/composer:latest-bin /composer /usr/local/bin/composer

RUN apt-get update --fix-missing && \
    apt-get install -y \
    git unzip \
    libicu-dev \
    libpq-dev \
    libssh-dev \
    librabbitmq-dev \
    libmemcached-dev \
    libkrb5-dev && rm -r /var/lib/apt/lists/* \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && docker-php-ext-install bcmath \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]