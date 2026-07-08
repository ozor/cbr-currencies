.PHONY: build up down restart warmup test logs-app logs-worker shell ps stan cs-check cs-fix

# ──────────────────────────────────────────────
#  Build & Lifecycle
# ──────────────────────────────────────────────

## Сборка образов
build:
	docker-compose build

## Запуск всего стека (без warmup)
up:
	docker-compose up -d

## Сборка и запуск за один шаг
build-up:
	docker-compose up --build -d

## Остановка и удаление контейнеров
down:
	docker-compose down

## Перезапуск всего стека
restart:
	docker-compose restart

# ──────────────────────────────────────────────
#  Warmup исторических данных (явная операция)
# ──────────────────────────────────────────────

## Прогрев кеша курсов за последние 180 дней.
## Запускать вручную после старта стека.
## Команда диспатчит сообщения в RabbitMQ; worker обрабатывает их асинхронно.
warmup:
	docker-compose exec app php bin/console app:cbr:warmup-rates --days=180

## Прогрев за произвольное количество дней: make warmup-days DAYS=30
warmup-days:
	docker-compose exec app php bin/console app:cbr:warmup-rates --days=$(DAYS)

# ──────────────────────────────────────────────
#  Тесты
# ──────────────────────────────────────────────

## Запуск PHPUnit тестов
test:
	docker-compose exec app vendor/bin/phpunit

## Run PHPStan (level 8) inside app container
stan:
	docker-compose exec app composer stan

## Run PHP CS Fixer (dry-run check) inside app container
cs-check:
	docker-compose exec app composer cs-check

## Run PHP CS Fixer and apply fixes inside app container
cs-fix:
	docker-compose exec app composer cs-fix

full-check:
	make stan cs-check test

# ──────────────────────────────────────────────
#  Операционные команды
# ──────────────────────────────────────────────

## Логи app-контейнера
logs-app:
	docker-compose logs -f app

## Логи worker-контейнера
logs-worker:
	docker-compose logs -f worker

## Bash-сессия в app-контейнере
shell:
	docker-compose exec app bash

## Статус контейнеров
ps:
	docker-compose ps

## Статус очередей Messenger
messenger-stats:
	docker-compose exec app php bin/console messenger:stats

## Failed сообщения
failed-show:
	docker-compose exec app php bin/console messenger:failed:show

## Повторная обработка failed сообщений
failed-retry:
	docker-compose exec app php bin/console messenger:failed:retry
