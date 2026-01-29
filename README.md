
# CBR Currency Rates API

Сервис для получения курсов валют и кросс-курсов с сайта Центрального Банка России (CBR.RU)

## Задача

- Получение курсов, кросскурсов Центрального Банка
- PHP в качестве основного языка программирования
- Получать курсы с http://cbr.ru
- Кешировать получаемые данные
- Реализовать сбор данных с cbr за 180 предыдущих дней с помощью воркера через консольную команду используя брокер сообщений

### Входные параметры
- **Дата** - дата курса валюты
- **Код валюты** - трёхбуквенный код (например: USD, EUR)
- **Базовая валюта** - по умолчанию RUR (опционально)

### Выходные данные
- **Значение курса** - текущий курс валюты
- **Разница с предыдущим торговым днём** - изменение курса

## Установка

### Предварительные требования

> Docker и Docker Compose должны быть установлены на вашей системе.

#### Установка Docker и Docker Compose на Linux

Инструкция ниже содержит все необходимые команды для установки Docker и Docker Compose 
на большинстве Linux дистрибутивов (Ubuntu, Debian, Mint и др.)

1. **Обновите пакеты системы:**
   ```bash
   sudo apt-get update
   sudo apt-get upgrade -y
   ```

2. **Установите Docker:**
   ```bash
   curl -fsSL https://get.docker.com -o get-docker.sh
   sudo sh get-docker.sh
   sudo usermod -aG docker $USER
   ```

3. **Установите Docker Compose:**
   ```bash
   sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
   sudo chmod +x /usr/local/bin/docker-compose
   ```

4. **Проверьте установку:**
   ```bash
   docker --version
   docker-compose --version
   ```

5. **Перезагрузите систему или перелогиньтесь** для применения изменений группы.

### Пошаговая инструкция по установке проекта

1. **Клонируйте репозиторий:**
```bash
git clone https://github.com/ozor/cbr-currencies.git
cd cbr-currencies/php-symfony-rabbit
```

2. **Запустите Docker контейнеры:**
```bash
docker-compose up --build -d
```

3. **Дождитесь инициализации** (около 30-60 секунд):

> - Ожидание готовности Redis и RabbitMQ
> - Установка зависимости (если требуется)
> - Очистка кеша Symfony
> - Предзагрузка кеша курсов валют за последние 180 дней
> - Запуск Messenger Worker для обработки сообщений из RabbitMQ

Сразу после старта до полной инициализации может возвращать 502 статус ответа. Это нормально.

4. **Проверьте готовность системы:**
```bash
# Проверка статуса контейнеров
docker-compose ps

# Проверка доступности API
curl http://localhost:8090/api/v1/cbr/rates/2024-01-10/USD
```

## Использование и работа с API

### API Endpoints

#### Получение курса валюты к рублю
```bash
curl -X GET http://localhost:8090/api/v1/cbr/rates/2021-10-01/USD
```

**Пример в браузере:**
- http://localhost:8090/api/v1/cbr/rates/2021-10-01/USD

#### Получение кросс-курса валют
```bash
curl -X GET http://localhost:8090/api/v1/cbr/rates/2021-10-01/USD/JPY
```

**Пример в браузере:**
- http://localhost:8090/api/v1/cbr/rates/2021-10-01/USD/JPY

### Документация API

Интерактивная документация OpenAPI (Swagger UI) доступна по адресу:
- **http://localhost:8090/api/doc**

### Тестирование

Запуск unit-тестов:
```bash
docker-compose exec app vendor/bin/phpunit
```

### Работа с брокером сообщений для сбора исторических данных

Для выполнения требования "сбор данных с cbr за 180 предыдущих дней с помощью воркера через консольную команду" 
реализована rонсольная команда, использующая отправку в очередь с помощью Symfony Messenger.

#### Консольная команда
```bash
# Запуск сбора данных за 180 дней (согласно ТЗ)
docker-compose exec app php bin/console app:cbr:warmup-cache --days=180
```

#### Как работает:
- Команда создает 180 сообщений (по одному на каждый день)
- Отправляет их в RabbitMQ очередь cbr_rates_queue
- Завершается мгновенно, не дожидаясь обработки
- Worker обрабатывает сообщения асинхронно в фоновом режиме
- Каждое сообщение приводит к запросу к CBR.RU и сохранению результата в Redis кеш

### Мониторинг работы системы

**Проверка статуса контейнеров:**
```bash
docker-compose ps
```

Вы должны увидеть 5 запущенных контейнеров:
- `app` - PHP-FPM приложение
- `worker` - Messenger Worker для обработки очереди
- `nginx` - Web-сервер
- `redis` - Кеш
- `rabbitmq` - Брокер сообщений

**Логи worker:**
```bash
docker-compose logs -f worker
```

**Логи приложения:**
```bash
docker-compose logs -f app
```

**RabbitMQ Management UI:**

Откройте http://localhost:15673

Логин и пароль можно найти в `.env` файле переменных окружения:
- **Логин:**: `RABBITMQ_DEFAULT_USER`
- **Пароль:**: `RABBITMQ_DEFAULT_PASS`

Здесь вы можете:
- Просматривать очереди и количество сообщений
- Мониторить производительность worker'ов
- Анализировать failed сообщения

### Ручное управление кешем (опционально)

Если нужно обновить кеш вручную:

```bash
# Обновить кеш за последние 7 дней
docker-compose exec app php bin/console app:cbr:warmup-cache --days=7

# Обновить кеш за последние 90 дней
docker-compose exec app php bin/console app:cbr:warmup-cache --days=90

# Обновить кеш с определенной даты
docker-compose exec app php bin/console app:cbr:warmup-cache "2024-01-01" --days=10
```

## Конфигурация Symfony Messenger

### Транспорты

Система использует следующие транспорты (настроены в `config/packages/messenger.yaml`):

1. **sync** - синхронная обработка сообщений
   - DSN: `sync://`
   - Используется для: немедленная обработка запросов к CBR API
   
2. **async** - асинхронная обработка через RabbitMQ
   - DSN: `amqp://rabbitmq:rabbitmq@rabbitmq:5672/%2f/messages`
   - Exchange: `cbr_rates_exchange` (type: direct)
   - Queue: `cbr_rates_queue`
   - Retry strategy: 3 попытки с экспоненциальной задержкой
   - Используется для: фоновое обновление кеша

3. **failed** - хранение неудачных сообщений
   - DSN: `doctrine://default?queue_name=failed`
   - Для анализа и повторной обработки

### Маршрутизация сообщений

```yaml
# config/packages/messenger.yaml
routing:
    # API запросы - синхронная обработка для немедленного ответа
    'App\Messenger\Message\CbrRatesRequestMessage': sync
    
    # Обновление кеша - асинхронная обработка через RabbitMQ
    'App\Messenger\Message\CbrRatesCacheUpdateMessage': async
```

### Автоматический Messenger Worker

Messenger Worker запускается автоматически в отдельном Docker контейнере (`worker`) и постоянно обрабатывает сообщения из RabbitMQ очереди:

- Автоматический запуск при старте системы
- Автоматический перезапуск при сбоях
- Ограничение времени работы: 1 час (затем перезапуск)
- Ограничение памяти: 256MB
- Лимит сообщений: 1000 за цикл

Для просмотра логов worker:
```bash
docker-compose logs -f worker
```

## Переменные окружения

Ключевые переменные описаны в `.env`

## Мониторинг и отладка

### RabbitMQ Management UI
- URL: http://localhost:15673
- Логин: `rabbitmq` / Пароль: `rabbitmq`
- Просмотр очередей, exchanges, сообщений

### Полезные команды

```bash
# Список доступных транспортов
docker-compose exec app php bin/console messenger:stats

# Обработка сообщений с подробным выводом
docker-compose exec app php bin/console messenger:consume async -vv

# Просмотр failed сообщений
docker-compose exec app php bin/console messenger:failed:show

# Повторная обработка failed сообщений
docker-compose exec app php bin/console messenger:failed:retry

# Очистка всех failed сообщений
docker-compose exec app php bin/console messenger:failed:remove

# Просмотр логов
docker-compose logs -f app

# Проверка кеша Redis
docker-compose exec redis redis-cli
> KEYS *
> GET "cache_key"
> TTL "cache_key"
```

## Структура проекта

```
php-symfony-rabbit/
├── config/                    # Конфигурационные файлы
│   ├── packages/
│   │   ├── messenger.yaml     # Конфигурация Symfony Messenger
│   │   ├── cache.yaml         # Redis кеширование
│   │   └── framework.yaml     # HTTP клиент для CBR.RU
│   └── services.yaml          # Dependency Injection
├── src/
│   ├── Command/               # Console команды
│   │   └── CbrWarmupCacheCommand.php  # Предзагрузка кеша
│   ├── Controller/            # API контроллеры
│   │   └── CbrController.php  # REST API endpoints
│   ├── Service/               # Бизнес-логика
│   │   └── CbrRates/
│   │       ├── CbrRatesSupplier.php      # Поставщик данных CBR
│   │       └── CbrRatesSupplierProxy.php # Прокси с кешированием
│   ├── Messenger/             # RabbitMQ обработчики
│   │   ├── Message/
│   │   │   ├── CbrRatesRequestMessage.php        # Запрос к CBR API
│   │   │   └── CbrRatesCacheUpdateMessage.php    # Обновление кеша
│   │   └── MessageHandler/
│   │       ├── CbrRequestHandler.php             # Обработчик запросов CBR
│   │       └── CbrRatesCacheUpdateHandler.php    # Обработчик обновления кеша
│   ├── Repository/            # Работа с данными
│   ├── Dto/                   # Data Transfer Objects
│   ├── Validator/             # Валидация запросов
│   └── Exception/             # Обработка исключений
├── tests/                     # Тесты
├── docker/                    # Docker конфигурация
│   ├── nginx/
│   └── php-fpm/
└── docker-compose.yaml        # Оркестрация контейнеров
```

### Основные возможности

- **Получение курсов валют** - актуальные данные с cbr.ru
- **Расчет кросс-курсов** - обмен между любыми валютами
- **Динамика курсов** - разница с предыдущим торговым днем
- **Автоматическая предзагрузка кеша** - система готова к работе сразу после запуска
- **Мгновенные ответы API** - все запросы обрабатываются из Redis кеша (< 10ms)
- **Фоновая обработка** - автоматический Messenger Worker для обновления данных
- **Высокая надежность** - retry механизм и автоматический перезапуск
- **API документация** - интерактивный Swagger UI

### Архитектура обработки запросов

Система использует **асинхронную архитектуру** с предзагрузкой кеша для обеспечения максимальной производительности:

#### Принцип работы

1. **При запуске системы:**
   - Автоматически загружаются курсы валют за последние 30 дней в Redis кеш
   - Запускается Messenger Worker для обработки очереди RabbitMQ
   - Система готова к работе с предзагруженными данными

2. **При API запросе:**
   - **Все запросы** обрабатываются мгновенно из Redis кеша (< 10ms)
   - Данные всегда актуальны благодаря предзагрузке
   - Нет задержек на запросы к ЦБ РФ

3. **Фоновое обновление:**
   - **Messenger Worker** постоянно работает в фоновом режиме
   - Обрабатывает сообщения из RabbitMQ очереди
   - Поддержка retry-стратегии при сбоях (3 попытки с экспоненциальной задержкой)
   - Автоматический перезапуск при сбоях

## Технологический стек

- **PHP** - 8.5 - основной язык программирования
- **Symfony** - 8.0 - веб-фреймворк
- **RabbitMQ** - latest - брокер сообщений
- **Redis** - latest - кеширование данных
- **Docker** - latest - контейнеризация
- **PHPUnit** - latest - тестирование

### Ключевые компоненты

- **CbrController** - REST API endpoint для клиентов
- **CbrRatesSupplierProxy** - кеширующий прокси (Redis)
- **CbrRatesSupplier** - поставщик данных через Messenger
- **CbrRequestHandler** - HTTP запросы к CBR.RU
- **CbrRatesCacheUpdateHandler** - фоновое обновление кеша
- **CbrWarmupCacheCommand** - предзагрузка кеша

## Полезные ссылки

- [Symfony Messenger Documentation](https://symfony.com/doc/current/messenger.html)
- [RabbitMQ Tutorials](https://www.rabbitmq.com/tutorials)
- [Redis Documentation](https://redis.io/docs/)
- [CBR.RU API](http://www.cbr.ru/development/)

