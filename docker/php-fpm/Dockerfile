FROM php:8.5-fpm

COPY --from=composer/composer:latest-bin /composer /usr/local/bin/composer

RUN apt-get update --fix-missing && \
    apt-get install -y \
    git unzip netcat-openbsd \
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

# Устанавливаем зависимости на этапе сборки образа (включая dev для PHPUnit)
COPY composer.json composer.lock ./
RUN composer install --optimize-autoloader --no-interaction

# Копируем весь проект поверх
COPY . .

# Копируем entrypoint скрипты
COPY docker/php-fpm/docker-entrypoint.sh /usr/local/bin/
COPY docker/php-fpm/worker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh /usr/local/bin/worker-entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]

CMD ["php-fpm"]