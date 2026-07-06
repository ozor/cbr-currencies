# CBR Currency Rates API

![CI](https://github.com/ozor/cbr-currencies/actions/workflows/ci.yml/badge.svg)

Сервис для получения курсов валют и кросс-курсов с сайта Центрального Банка России (cbr.ru).

## Задача

- Получение курсов и кросс-курсов ЦБ РФ
- PHP в качестве основного языка программирования
- Данные загружаются с http://cbr.ru
- Кеширование полученных данных (Redis)
- Сбор данных за 180 предыдущих дней через консольную команду + брокер сообщений (RabbitMQ + Symfony Messenger)

### Входные параметры

| Параметр   | Описание                                           | Пример       |
|------------|----------------------------------------------------|--------------|
| `date`     | Дата курса (`Y-m-d`)                               | `2024-01-10` |
| `code`     | Трёхбуквенный код валюты                           | `USD`, `EUR` |
| `baseCode` | Базовая валюта (необязательно, по умолчанию `RUR`) | `JPY`        |

### Выходные данные

- Значение курса (с учётом базовой валюты)
- Разница с предыдущим торговым днём

---

## Предварительные требования

Docker и Docker Compose должны быть установлены на вашей системе.

<details>
<summary>Установка Docker на Linux (Ubuntu/Debian/Mint)</summary>

```bash
# Установка Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER

# Установка Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" \
  -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Проверка
docker --version
docker-compose --version
```

Перезайдите в систему для применения изменений группы.

</details>

---

## Lifecycle (build / run / warmup)

Проект разделён на три явных этапа:

| Этап       | Что происходит                                                                              |
|------------|---------------------------------------------------------------------------------------------|
| **Build**  | Сборка Docker-образа, установка PHP-зависимостей (`composer install`)                       |
| **Run**    | Запуск web-приложения (PHP-FPM + Nginx), worker (Messenger consumer), Redis, RabbitMQ       |
| **Warmup** | Явная операция: диспатч сообщений в RabbitMQ → worker прогревает Redis-кеш за нужный период |

> Warmup **не запускается автоматически при старте** — это намеренное решение.
> API работает и без прогретого кеша (данные загружаются с cbr.ru при первом запросе).
> Warmup — отдельный шаг для предзагрузки исторических данных.

---

## Быстрый старт

```bash
# 1. Клонировать репозиторий
git clone https://github.com/ozor/cbr-currencies.git
cd cbr-currencies

# 2. Собрать образы и поднять стек
make build-up

# 3. Дождаться готовности контейнеров
make ps

# 4. (Опционально) Прогреть кеш за 180 дней
make warmup

# 5. Запустить тесты
make test
```

Или без Makefile:

```bash
# Сборка и запуск
docker-compose up --build -d

# Прогрев кеша (явно, отдельным шагом)
docker-compose exec app php bin/console app:cbr:warmup-rates --days=180

# Тесты
docker-compose exec app vendor/bin/phpunit
```

---

## API

### GET /api/v1/cbr/rates/{date}/{code}

Получение курса валюты к RUR:

```bash
curl http://localhost:8090/api/v1/cbr/rates/2024-01-10/USD
```

### GET /api/v1/cbr/rates/{date}/{code}/{baseCode}

Кросс-курс:

```bash
curl http://localhost:8090/api/v1/cbr/rates/2024-01-10/USD/JPY
```

### Примеры ответов

**Успешный ответ:**
```json
{
  "value": 89.5,
  "diff": 0.35
}
```

**Ошибка:**
```json
{
  "error": {
    "code": "rate_not_found",
    "message": "Rate not found."
  }
}
```

### Документация API (Swagger UI)

`http://localhost:8090/api/doc`

---

## Warmup исторических данных

Warmup диспатчит сообщения в RabbitMQ (по одному на рабочий день), не ожидая обработки.
Worker забирает сообщения из очереди и прогревает Redis-кеш асинхронно.

```bash
# Прогрев за 180 дней (согласно ТЗ)
make warmup
# или
docker-compose exec app php bin/console app:cbr:warmup-rates --days=180

# Прогрев за произвольное количество дней
make warmup-days DAYS=30

# Прогрев с произвольной даты
docker-compose exec app php bin/console app:cbr:warmup-rates 2024-01-01 --days=10
```

Прогресс обработки можно наблюдать в логах worker:

```bash
make logs-worker
```

---

## Тесты

```bash
make test
# или
docker-compose exec app vendor/bin/phpunit
```

---

## Качество кода

Набор команд для локальной проверки стиля, статического анализа и тестов (в контейнере):

```bash
# Все проверки разом (стиль + статанализ + тесты)
docker-compose exec app composer check

# По отдельности
docker-compose exec app composer cs-check   # проверка code style
docker-compose exec app composer cs-fix     # автоисправление стиля
docker-compose exec app composer stan       # PHPStan, level 8
docker-compose exec app composer test       # PHPUnit
```

---

## Мониторинг и операционные команды

```bash
# Статус контейнеров
make ps

# Логи app
make logs-app

# Логи worker
make logs-worker

# Bash в контейнере app
make shell

# Статистика Messenger
make messenger-stats

# Failed сообщения
make failed-show
make failed-retry

# Перезапуск стека
make restart

# Остановка
make down
```

### RabbitMQ Management UI

`http://localhost:15673` (логин/пароль из `.env`: `RABBITMQ_DEFAULT_USER` / `RABBITMQ_DEFAULT_PASS`)

---

## Переменные окружения

Ключевые переменные описаны в `.env`. Обязательные для старта:

| Переменная                | Описание                |
|---------------------------|-------------------------|
| `REDIS_DSN`               | DSN для Redis           |
| `MESSENGER_TRANSPORT_DSN` | DSN для RabbitMQ (AMQP) |
| `RABBITMQ_DEFAULT_USER`   | Логин RabbitMQ          |
| `RABBITMQ_DEFAULT_PASS`   | Пароль RabbitMQ         |
| `RABBITMQ_ERLANG_COOKIE`  | Erlang cookie RabbitMQ  |

---

## Структура проекта

```
├── Makefile                    # Операционные команды
├── docker-compose.yaml         # Оркестрация контейнеров
├── docker/
│   ├── nginx/conf.d/           # Nginx конфигурация
│   └── php-fpm/
│       ├── Dockerfile          # Образ (включает composer install)
│       ├── docker-entrypoint.sh  # App entrypoint: cache:clear + php-fpm
│       └── worker-entrypoint.sh  # Worker entrypoint: setup-transports + consume
├── config/                     # Конфигурационные файлы Symfony
│   ├── packages/
│   │   ├── messenger.yaml      # Транспорты RabbitMQ / in-memory (test)
│   │   ├── cache.yaml          # Redis кеш
│   │   └── framework.yaml      # HTTP клиент CBR с retry
│   └── services.yaml           # DI: привязки интерфейсов
├── src/
│   ├── Command/
│   │   └── WarmupRatesCommand.php          # app:cbr:warmup-rates
│   ├── Controller/
│   │   └── CbrController.php               # GET /api/v1/cbr/rates/...
│   ├── Service/CbrRates/
│   │   ├── CbrRatesCalculator.php          # Основная бизнес-логика
│   │   ├── CbrRatesSupplier.php            # HTTP → cbr.ru + XML parser
│   │   └── RateFinder.php                  # Поиск курса в snapshot
│   ├── Infrastructure/Cache/
│   │   └── CachedRatesProvider.php         # Redis cache layer
│   ├── Domain/Calendar/
│   │   └── PreviousTradingDayResolver.php  # Поиск предыдущего торгового дня
│   ├── Messenger/
│   │   ├── Message/WarmupRatesMessage.php
│   │   └── MessageHandler/WarmupRatesMessageHandler.php
│   └── Exception/                          # Иерархия исключений + ExceptionHandler
├── tests/                      # PHPUnit тесты (unit + functional)
└── scripts/                    # Дополнительные операционные скрипты
```

---

## Архитектура

```
GET /api/v1/cbr/rates/{date}/{code}/{baseCode?}
        │
        ▼
  CbrController
        │
        ▼
  CbrRatesValidator  ──── ValidationException (400)
        │
        ▼
  CbrRatesCalculator
        │  ├── RateFinder ─────────────────────── RateNotFoundException (404)
        │  └── PreviousTradingDayResolver ──────── PreviousTradingDayNotFoundException (404)
        │
        ▼
  CachedRatesProvider (Redis, TTL 86400)
        │  cache miss ──▶ CbrRatesSupplier
        │                      │── CbrHttpClient ─── UpstreamUnavailableException (502)
        │                      └── XmlRateParser ─── ParseRatesException (502)
        ▼
  CbrRateDto (snapshot)

Async warmup:
  WarmupRatesCommand (app:cbr:warmup-rates --days=N)
        │ dispatch WarmupRatesMessage per workday
        ▼
  RabbitMQ (async transport)
        ▼
  WarmupRatesMessageHandler → CachedRatesProvider::getDailyByDate() → Redis
```

---

## Технологический стек

| Технология        | Версия       | Роль             |
|-------------------|--------------|------------------|
| PHP               | 8.5          | Основной язык    |
| Symfony           | 8.0          | Веб-фреймворк    |
| Symfony Messenger | —            | Async pipeline   |
| RabbitMQ          | 3-management | Брокер сообщений |
| Redis             | latest       | Кеш (TTL 86400)  |
| Docker / Compose  | latest       | Контейнеризация  |
| PHPUnit           | latest       | Тестирование     |

---

## Полезные ссылки

- [Symfony Messenger](https://symfony.com/doc/current/messenger.html)
- [RabbitMQ Tutorials](https://www.rabbitmq.com/tutorials)
- [Redis Documentation](https://redis.io/docs/)
- [CBR.RU API](http://www.cbr.ru/development/)

