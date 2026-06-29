# AGENTS Guide (cbr-currencies)

## Scope and source of truth
- Primary app is `php-symfony-rabbit/` (Symfony 8 + PHP 8.5).
- `BACKUP/` is an older snapshot; do not copy patterns from there unless explicitly asked.
- When docs conflict, trust runtime config/code first: `config/packages/*.yaml`, `config/routes/*.yaml`, `src/**`.

## Big picture architecture
- HTTP entrypoint: `GET /api/v1/cbr/rates/{date}/{code}/{baseCode?}` in `src/Controller/CbrController.php`.
- API flow: controller -> validator -> calculator -> **cached provider** -> supplier.
- `CbrRatesCalculator` depends on `RatesProviderInterface` (bound to `CachedRatesProvider`), `RateFinder`, and `PreviousTradingDayResolver`.
- `RateFinder` (`src/Service/CbrRates/RateFinder.php`) locates a `CbrRateDto` inside a `CbrRatesDto` snapshot; throws `CbrRateNotFoundException` when absent.
- Fetching a daily snapshot is the responsibility of `RatesProviderInterface`; looking up a single rate inside a snapshot is the responsibility of `RateFinder`; finding the previous available trading date is the responsibility of `PreviousTradingDayResolver`.
- `PreviousTradingDayResolver` (`src/Domain/Calendar/PreviousTradingDayResolver.php`) determines the previous available trading date by walking back up to `MAX_LOOKBACK_DAYS = 15` days and probing `RatesProviderInterface::getDailyByDate()`; throws `PreviousTradingDayNotFoundException` if no snapshot is found within the limit.
- `CachedRatesProvider` (`src/Infrastructure/Cache/CachedRatesProvider.php`) is the single cache layer for daily snapshots (cache key: `CbrRatesDaily.{Y-m-d}`, TTL 86400, Redis via Symfony Cache); on cache `Throwable` it falls back to direct `CbrRatesSupplier` call.
- Cache miss calls CBR directly via `CbrHttpClient` + `XmlRateParser` inside `CbrRatesSupplier`.
- Background warmup uses `CbrRatesCacheUpdateMessage` -> `async` transport -> RabbitMQ -> `CbrRatesCacheUpdateHandler`.
- Single cache layer (Redis via Symfony Cache): snapshot key `CbrRatesDaily.{Y-m-d}` in `CachedRatesProvider`.

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
- `CbrRatesCacheUpdateHandler` uses `RatesProviderInterface` (resolved to `CachedRatesProvider`) so warmup primes exactly the same cache layer the API reads from.
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
  - `CbrRatesCalculatorInterface` -> `CbrRatesCalculator` (direct, no proxy);
  - `CbrRatesSupplierInterface` -> `CbrRatesSupplier`;
  - `RatesProviderInterface` -> `CachedRatesProvider` (used by `CbrRatesCalculator` for snapshot fetching and by `PreviousTradingDayResolver` for trading-day probing).
- `RatesProviderInterface` and `CbrRatesSupplierInterface` are two independent interfaces; `CbrRatesSupplier` implements both. `CachedRatesProvider` implements only `RatesProviderInterface` and wraps `CbrRatesSupplier` (injected as concrete class to avoid circular DI).
- `CachedRatesProvider` catches cache-layer `Throwable`, logs context, and falls back to direct `CbrRatesSupplier` call; keep this resilience pattern when adding new cache decorators.
- `PreviousTradingDayResolver` (`src/Domain/Calendar/PreviousTradingDayResolver.php`) is a pure domain service: it accepts a `DateTimeImmutable` date and returns the nearest previous date for which `RatesProviderInterface::getDailyByDate()` returns a non-null snapshot; walks back one day at a time; constant `MAX_LOOKBACK_DAYS = 15`; throws `PreviousTradingDayNotFoundException` (extends `NotFoundHttpException`, implements `CbrRatesExceptionInterface`) when no snapshot is found within the limit. Does **not** contain HTTP, XML, or cache logic.
- `CbrRatesCalculator` no longer contains calendar arithmetic; it delegates previous-trading-day resolution to `PreviousTradingDayResolver` injected via constructor.
- For async handlers, rethrow exceptions to activate Messenger retry (`CbrRatesCacheUpdateHandler`).
- Tests are mostly unit tests with mocks/stubs under `tests/**`; keep new tests isolated from external services. In `test` env, Messenger `async` transport is overridden to `in-memory://` (`config/packages/messenger.yaml`).
- `CachedRatesProvider` is tested in `tests/Infrastructure/Cache/CachedRatesProviderTest.php`.
- `PreviousTradingDayResolver` is tested in `tests/Domain/Calendar/PreviousTradingDayResolverTest.php`; scenarios: first attempt success, skip unavailable weekend days, find within limit, throw when limit exhausted (15 attempts).
