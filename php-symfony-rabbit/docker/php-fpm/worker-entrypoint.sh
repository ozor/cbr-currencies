#!/bin/bash
set -e

echo "Starting Messenger Worker..."

# Инициализация транспортов (идемпотентная операция)
php bin/console messenger:setup-transports

echo "Worker transports ready. Starting consumer..."

# Запуск consumer.
# Docker restart policy (restart: always) обеспечивает перезапуск при выходе.
exec php bin/console messenger:consume async \
  --time-limit=3600 \
  --memory-limit=256M \
  --limit=1000 \
  -vv
