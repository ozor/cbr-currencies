#!/bin/bash
set -e

echo "Starting Messenger Worker..."

# Ждем готовности основного приложения
sleep 10

# Инициализация транспортов
php bin/console messenger:setup-transports

# Запуск worker с автоматическим перезапуском
while true; do
  php bin/console messenger:consume async \
    --time-limit=3600 \
    --memory-limit=256M \
    --limit=1000 \
    -vv || true

  sleep 5
done
