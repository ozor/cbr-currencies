# Получение курсов, кроскурсов ЦБ

## Требования:
- на входе: дата, код валюты, код базовой валюты (по-умолчанию RUR);
- получать курсы с http://cbr.ru;
- на выходе: значение курса и разница с предыдущим торговым днем;
- кешировать данные http://cbr.ru.
- учесть, что cbr блокирует частые запросы с одинаковым промежутком, поэтому реализовать сбор данных с использованием брокера сообщений.

## Реализация:
- PHP 8.2
- Symfony 6.3
- RabbitMQ, Redis
- Docker, Docker-compose
- PHPUnit

## Установка:
1. Docker и Docker-compose должны быть установлены.
    - Если есть затруднения, то эта статья может помочь: [Step-by-Step Tutorial: Installing Docker and Docker Compose on Ubuntu](https://medium.com/@tomer.klein/step-by-step-tutorial-installing-docker-and-docker-compose-on-ubuntu-a98a1b7aaed0).
    - Так же инструкция с официального сайта: [Install Docker Engine on Ubuntu](https://docs.docker.com/engine/install/ubuntu/).
2. После того как Docker и Docker-compose установлены, можно приступать к самой установке: клонировать репозиторий и запустить контейнеры:
    - `git clone https://github.com/ozor/cbr-currencies.git`
    - `cd cbr-currencies/php-symfony-rabbit`
    - `docker-compose up --build -d`
    - `docker-compose exec app composer install`

## Использование:
- Пример запросов на получение курса валют через CURL:
    - `curl -X GET http://localhost:8090/api/v1/cbr/rates/2021-10-01/USD`
    - `curl -X GET http://localhost:8090/api/v1/cbr/rates/2021-10-01/USD/JPY`
- Запросы так же доступны в браузере:
    - http://localhost:8090/api/v1/cbr/rates/2021-10-01/USD
    - http://localhost:8090/api/v1/cbr/rates/2021-10-01/USD/JPY
- Документация OpenAPI (Swagger): 
    - http://localhost:8090/api/doc
- Запуск тестов: 
    - `docker-compose exec app vendor/bin/phpunit`