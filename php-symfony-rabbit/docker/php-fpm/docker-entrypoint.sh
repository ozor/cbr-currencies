#!/bin/bash
set -e

echo "Starting application initialization..."

# Ждем готовности зависимостей

# Ждем Redis
until nc -z redis 6379; do
  sleep 2
done

# Ждем RabbitMQ
until nc -z rabbitmq 5672; do
  sleep 2
done

# Ждем дополнительно для полной инициализации RabbitMQ
sleep 5

# Установка зависимостей если нужно
if [ ! -d "vendor" ]; then
  composer install --no-interaction --optimize-autoloader
fi

# Очистка кеша
php bin/console cache:clear --no-interaction

# Прогрев кеша курсов валют за последние 180 дней
php bin/console app:cbr:warmup-cache --days=180 || true

echo "Application is ready!"

# Выполнить переданную команду
exec "$@"
