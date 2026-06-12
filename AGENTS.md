# AGENTS Guide (cbr-currencies)

## Scope and source of truth
- Primary app is `php-symfony-rabbit/` (Symfony 8 + PHP 8.5).
- `BACKUP/` is an older snapshot; do not copy patterns from there unless explicitly asked.
- When docs conflict, trust runtime config/code first: `config/packages/*.yaml`, `config/routes/*.yaml`, `src/**`.

## Big picture architecture
- HTTP entrypoint: `GET /api/v1/cbr/rates/{date}/{code}/{baseCode?}` in `src/Controller/CbrController.php`.
- API flow: controller -> validator -> calculator proxy -> calculator -> repository -> supplier proxy -> supplier.
- External CBR call is wrapped as Messenger message `CbrRatesRequestMessage` handled by `CbrRequestHandler`.
- `CbrRatesRequestMessage` is routed to `sync` transport (`config/packages/messenger.yaml`), so cache miss is still in-request.
- Background warmup uses `CbrRatesCacheUpdateMessage` -> `async` transport -> RabbitMQ -> `CbrRatesCacheUpdateHandler`.
- Two cache layers (Redis via Symfony Cache):
  - supplier cache key: `CbrRatesDaily.{Y-m-d}` in `CbrRatesSupplierProxy`;
  - calculator cache key: `CbrRatesDailyCalculator.{date}.{code}.{baseCode}` in `CbrRatesCalculatorProxy`.

## Data and validation conventions
- Request date format is strict `Y-m-d` (`src/Config/CbrRates.php` + `CbrRateRequestDto` constraints).
- Base currency default is `RUR`; supplier explicitly appends synthetic `RUR=1.0` rate (`CbrRatesSupplier`).
- Currency precision is centralized (`CbrRates::CURRENCY_VALUE_PRECISION`) and applied in DTO/calculator.
- API errors are normalized by `src/Exception/ExceptionHandler.php`:
  - validation -> `400` with `errorMessage` + `errors` map;
  - not found -> `404` with `errorMessage`.

## Messaging and integration boundaries
- RabbitMQ DSN comes from `MESSENGER_TRANSPORT_DSN` (`.env`); async exchange/queue are declared in `messenger.yaml`.
- HTTP client `cbr_rates.client` is configured in `config/packages/framework.yaml` with retry strategy (429/500/network).
- `CbrRequestHandler` adds random sleep (100-500ms) before CBR call to reduce burst traffic.
- Redis backend is `cache.adapter.redis` (`config/packages/cache.yaml`, `REDIS_DSN`).

## Developer workflows that matter
- Start stack: `docker-compose up --build -d` from `php-symfony-rabbit/`.
- App container auto-runs cache warmup for 180 days on startup (`docker/php-fpm/docker-entrypoint.sh`).
- Worker container auto-consumes `async` in loop with limits (`docker/php-fpm/worker-entrypoint.sh`).
- Run tests: `docker-compose exec app vendor/bin/phpunit`.
- Useful operational scripts: `scripts/check-messenger.sh`, `scripts/test-complete.sh`.

## Project-specific coding/test patterns
- Use `readonly` DTO/service style and constructor promotion used across `src/Dto` and `src/Service`.
- Prefer contract injection via DI aliases in `config/services.yaml`:
  - `CbrRatesCalculatorInterface` -> `CbrRatesCalculatorProxy`;
  - `CbrRatesSupplierInterface` -> `CbrRatesSupplierProxy`.
- For async handlers, rethrow exceptions to activate Messenger retry (`CbrRatesCacheUpdateHandler`).
- Tests are mostly unit tests with mocks/stubs under `tests/**`; keep new tests isolated from external services.

