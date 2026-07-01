# AGENTS Guide (cbr-currencies)

## Scope and source of truth
- Primary app is `php-symfony-rabbit/` (Symfony 8 + PHP 8.5).
- `BACKUP/` is an older snapshot; do not copy patterns from there unless explicitly asked.
- When docs conflict, trust runtime config/code first: `config/packages/*.yaml`, `config/routes/*.yaml`, `src/**`.

## Big picture architecture
- HTTP entrypoint: `GET /api/v1/cbr/rates/{date}/{code}/{baseCode?}` in `src/Controller/CbrController.php`.
- API flow: controller -> validator -> calculator -> **cached provider** -> supplier.
- `CbrRatesCalculator` depends on `RatesProviderInterface` (bound to `CachedRatesProvider`), `RateFinder`, and `PreviousTradingDayResolver`.
- `RateFinder` (`src/Service/CbrRates/RateFinder.php`) locates a `CbrRateDto` inside a `CbrRatesDto` snapshot; throws `RateNotFoundException` when absent.
- Fetching a daily snapshot is the responsibility of `RatesProviderInterface`; looking up a single rate inside a snapshot is the responsibility of `RateFinder`; finding the previous available trading date is the responsibility of `PreviousTradingDayResolver`.
- `PreviousTradingDayResolver` (`src/Domain/Calendar/PreviousTradingDayResolver.php`) determines the previous available trading date by walking back up to `MAX_LOOKBACK_DAYS = 15` days and probing `RatesProviderInterface::getDailyByDate()`; throws `PreviousTradingDayNotFoundException` if no snapshot is found within the limit.
- `CachedRatesProvider` (`src/Infrastructure/Cache/CachedRatesProvider.php`) is the single cache layer for daily snapshots (cache key: `CbrRatesDaily.{Y-m-d}`, TTL 86400, Redis via Symfony Cache); on cache `Throwable` it falls back to direct `CbrRatesSupplier` call.
- Cache miss calls CBR directly via `CbrHttpClient` + `XmlRateParser` inside `CbrRatesSupplier`.
- Background warmup uses `WarmupRatesMessage` -> `async` transport -> RabbitMQ -> `WarmupRatesMessageHandler`.
- Single cache layer (Redis via Symfony Cache): snapshot key `CbrRatesDaily.{Y-m-d}` in `CachedRatesProvider`.

## Data and validation conventions
- Request date format is strict `Y-m-d` (`src/Config/CbrRates.php` + `CbrRateRequestDto` constraints).
- Base currency default is `RUR`; supplier explicitly appends synthetic `RUR=1.0` rate (`CbrRatesSupplier`).
- Currency precision is centralized (`CbrRates::CURRENCY_VALUE_PRECISION`) and applied in DTO/calculator.
- API errors are normalized by `src/Exception/ExceptionHandler.php` using a unified JSON contract:
  ```json
  { "error": { "code": "rate_not_found", "message": "Rate not found.", "details": { "field": "msg" } } }
  ```
  `details` is only present for validation errors.
- HTTP status policy (enforced via `instanceof` mapping, **not** `getCode()`):
  - `ValidationException` → `400`
  - `RateNotFoundException` / `PreviousTradingDayNotFoundException` → `404`
  - `UpstreamUnavailableException` → `502`
  - `ParseRatesException` → `502`
  - any other `Throwable` → `500`
- Machine-readable error codes are centralized in the `ErrorCode` enum (`src/Exception/ErrorCode.php`): `validation_error`, `rate_not_found`, `upstream_unavailable`, `parse_error`, `internal_error`.

## Exception model
Four semantic categories — each layer throws only its own category:

| Category               | Class                                                             | Thrown by                          |
|------------------------|-------------------------------------------------------------------|------------------------------------|
| Validation             | `ValidationException` (`src/Exception/`)                          | `CbrRatesValidator`                |
| Business not found     | `RateNotFoundException` (`src/Exception/CbrRates/`)               | `RateFinder`, `CbrRatesCalculator` |
| Business not found     | `PreviousTradingDayNotFoundException` (`src/Exception/CbrRates/`) | `PreviousTradingDayResolver`       |
| Upstream / integration | `UpstreamUnavailableException` (`src/Exception/CbrRates/`)        | `CbrHttpClient`                    |
| Parse                  | `ParseRatesException` (`src/Exception/CbrRates/`)                 | `XmlRateParser`                    |

- All domain exceptions implement `CbrRatesExceptionInterface`.
- Old class names (`CbrProviderException`, `CbrRatesParseException`, `CbrRateNotFoundException`, `RequestValidationException`) are **no longer used**; the files remain as orphaned stubs.
- `CachedRatesProvider` keeps its `catch (Throwable)` fallback intact (cache-resilience pattern); upstream/parse exceptions can still propagate from the retry path.

## Messaging and integration boundaries
- RabbitMQ DSN comes from `MESSENGER_TRANSPORT_DSN` (`.env`); `async` and `failed` transports are declared in `config/packages/messenger.yaml`.
- HTTP client `cbr_rates.client` is configured in `config/packages/framework.yaml` with retry strategy (429/500/network).
- There is no sync request message/handler path in current runtime config; Messenger is used for async warmup only.
- Async warmup pipeline: `WarmupRatesCommand` dispatches `WarmupRatesMessage` per date → `async` transport (RabbitMQ) → `WarmupRatesMessageHandler` calls `RatesProviderInterface::getDailyByDate()` (resolved to `CachedRatesProvider`) → primes exactly the same cache layer the sync API reads from.
- Redis backend is `cache.adapter.redis` (`config/packages/cache.yaml`, `REDIS_DSN`).

## Developer workflows that matter

The project lifecycle is split into three explicit stages: **build → run → warmup**.

- **Build**: `docker-compose build` from `php-symfony-rabbit/`. `composer install` runs inside the Docker build stage (Dockerfile) and is baked into the image. A Docker anonymous volume (`/var/www/html/vendor`) prevents the bind-mount from masking vendor at runtime.
- **Run stack**: `docker-compose up -d` (or `make up`). App and worker containers wait for Redis/RabbitMQ healthchecks before starting (no sleep-based hacks).
- **App entrypoint** (`docker/php-fpm/docker-entrypoint.sh`): runs `cache:clear` then starts `php-fpm`. No hidden business operations.
- **Worker entrypoint** (`docker/php-fpm/worker-entrypoint.sh`): runs `messenger:setup-transports`, then `exec`s `messenger:consume async` with limits. Docker `restart: always` handles re-runs when the process exits naturally (e.g. after `--time-limit`). No bash loop, no `|| true`.
- **Warmup** (explicit, separate step): `make warmup` or `docker-compose exec app php bin/console app:cbr:warmup-rates --days=180`. This dispatches messages to RabbitMQ; worker processes them asynchronously. Warmup is **not** triggered on app startup.
- **Run tests**: `make test` or `docker-compose exec app vendor/bin/phpunit`.
- **Makefile** (`php-symfony-rabbit/Makefile`) provides targets: `build`, `up`, `build-up`, `down`, `restart`, `warmup`, `warmup-days`, `test`, `logs-app`, `logs-worker`, `shell`, `ps`, `messenger-stats`, `failed-show`, `failed-retry`.

## Project-specific coding/test patterns
- Use `readonly` DTO/service style and constructor promotion used across `src/Dto` and `src/Service`.
- Prefer contract injection via DI aliases in `config/services.yaml`:
  - `CbrRatesCalculatorInterface` -> `CbrRatesCalculator` (direct, no proxy);
  - `CbrRatesSupplierInterface` -> `CbrRatesSupplier`;
  - `RatesProviderInterface` -> `CachedRatesProvider` (used by `CbrRatesCalculator` for snapshot fetching, by `PreviousTradingDayResolver` for trading-day probing, and by `WarmupRatesMessageHandler` for async warmup).
- `RatesProviderInterface` and `CbrRatesSupplierInterface` are two independent interfaces; `CbrRatesSupplier` implements both. `CachedRatesProvider` implements only `RatesProviderInterface` and wraps `CbrRatesSupplier` (injected as concrete class to avoid circular DI).
- `CachedRatesProvider` catches cache-layer `Throwable`, logs context, and falls back to direct `CbrRatesSupplier` call; keep this resilience pattern when adding new cache decorators.
- `PreviousTradingDayResolver` (`src/Domain/Calendar/PreviousTradingDayResolver.php`) is a pure domain service: it accepts a `DateTimeImmutable` date and returns the nearest previous date for which `RatesProviderInterface::getDailyByDate()` returns a non-null snapshot; walks back one day at a time; constant `MAX_LOOKBACK_DAYS = 15`; throws `PreviousTradingDayNotFoundException` (extends `NotFoundHttpException`, implements `CbrRatesExceptionInterface`) when no snapshot is found within the limit. Does **not** contain HTTP, XML, or cache logic.
- `CbrRatesCalculator` no longer contains calendar arithmetic; it delegates previous-trading-day resolution to `PreviousTradingDayResolver` injected via constructor.
- Async warmup classes:
  - `WarmupRatesCommand` (`src/Command/WarmupRatesCommand.php`): console command `app:cbr:warmup-rates`; pure orchestration — accepts start date + days, skips weekends, dispatches `WarmupRatesMessage` per workday via `MessageBusInterface`.
  - `WarmupRatesMessage` (`src/Messenger/Message/WarmupRatesMessage.php`): carries a single `DateTimeImmutable $date`.
  - `WarmupRatesMessageHandler` (`src/Messenger/MessageHandler/WarmupRatesMessageHandler.php`): calls `RatesProviderInterface::getDailyByDate()` to prime the cache layer; rethrows exceptions to activate Messenger retry.
- For async handlers, rethrow exceptions to activate Messenger retry (`WarmupRatesMessageHandler`).
- Tests are mostly unit tests with mocks/stubs under `tests/**`; keep new tests isolated from external services. In `test` env, Messenger `async` transport is overridden to `in-memory://` (`config/packages/messenger.yaml`).
- `ExceptionHandler` is unit-tested in `tests/Exception/ExceptionHandlerTest.php`; scenarios cover all five error categories, correct HTTP status, `error.code` value, presence/absence of `details`, and JSON content-type.
- Controller error scenarios are functional-tested in `tests/Controller/CbrControllerFunctionalTest.php` via `WebTestCase`; the mock `CbrRatesCalculatorInterface` is injected via `static::getContainer()->set()` (service is declared `public: true` in `when@test:` block of `config/services.yaml`); no real HTTP requests to CBR are made.
