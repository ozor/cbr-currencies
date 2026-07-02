#!/bin/bash
set -e

echo "Starting application..."

# Очистка кеша Symfony
php bin/console cache:clear --no-interaction

echo "Application is ready!"

# Выполнить переданную команду
exec "$@"
