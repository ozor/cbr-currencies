# AGENTS Guide (cbr-currencies)

## Scope and source of truth
- Primary app is `php-symfony-rabbit/` (Symfony 8 + PHP 8.5).
- `BACKUP/` is an older snapshot; do not copy patterns from there unless explicitly asked.
- When docs conflict, trust runtime config/code first: `config/packages/*.yaml`, `config/routes/*.yaml`, `src/**`.

## Big picture architecture
- HTTP entrypoint: `GET /api/v1/cbr/rates/{date}/{code}/{baseCode?}` in `src/Controller/CbrController.php`.
- API flow: controller -> validator -> calculator proxy -> calculator -> **provider** -> supplier proxy -> supplier.
- `CbrRatesCalculator` depends on `RatesProviderInterface` (bound to `CbrRatesSupplierProxy`) and `RateFinder`.
- `RateFinder` (`src/Service/CbrRates/RateFinder.php`) locates a `CbrRateDto` inside a `CbrRatesDto` snapshot; throws `CbrRateNotFoundException` when absent.
- Fetching a daily snapshot is the responsibility of `RatesProviderInterface`; looking up a single rate inside a snapshot is the responsibility of `RateFinder`.
- Cache miss calls CBR directly via `CbrHttpClient` + `XmlRateParser` inside `CbrRatesSupplier`.
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
- RabbitMQ DSN comes from `MESSENGER_TRANSPORT_DSN` (`.env`); `async` and `failed` transports are declared in `config/packages/messenger.yaml`.
- HTTP client `cbr_rates.client` is configured in `config/packages/framework.yaml` with retry strategy (429/500/network).
- There is no sync request message/handler path in current runtime config; Messenger is used for async cache warmup only.
- Redis backend is `cache.adapter.redis` (`config/packages/cache.yaml`, `REDIS_DSN`).

## Developer workflows that matter
- Start stack: `docker-compose up --build -d` from `php-symfony-rabbit/`.
- App container auto-runs cache warmup for 180 days on startup (`docker/php-fpm/docker-entrypoint.sh`); warmup command skips weekends.
- Worker container runs `messenger:setup-transports` and then auto-consumes `async` in loop with limits (`docker/php-fpm/worker-entrypoint.sh`).
- Run tests: `docker-compose exec app vendor/bin/phpunit`.
- Useful operational scripts: `scripts/check-messenger.sh`, `scripts/test-api.sh`, `scripts/test-cache.sh`, `scripts/test-complete.sh`.

## Project-specific coding/test patterns
- Use `readonly` DTO/service style and constructor promotion used across `src/Dto` and `src/Service`.
- Prefer contract injection via DI aliases in `config/services.yaml`:
  - `CbrRatesCalculatorInterface` -> `CbrRatesCalculatorProxy`;
  - `CbrRatesSupplierInterface` -> `CbrRatesSupplierProxy`;
  - `RatesProviderInterface` -> `CbrRatesSupplierProxy` (used by `CbrRatesCalculator` for snapshot fetching).
- `RatesProviderInterface` and `CbrRatesSupplierInterface` are two independent interfaces; both are implemented by `CbrRatesSupplier` and `CbrRatesSupplierProxy`.
- Cache proxies (`CbrRatesSupplierProxy`, `CbrRatesCalculatorProxy`) catch cache-layer `Throwable`, log context, and fallback to direct call; keep this resilience pattern.
- For async handlers, rethrow exceptions to activate Messenger retry (`CbrRatesCacheUpdateHandler`).
- Tests are mostly unit tests with mocks/stubs under `tests/**`; keep new tests isolated from external services. In `test` env, Messenger `async` transport is overridden to `in-memory://` (`config/packages/messenger.yaml`).

