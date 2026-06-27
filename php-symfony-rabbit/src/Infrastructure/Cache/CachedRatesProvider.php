<?php

namespace App\Infrastructure\Cache;

use App\Config\CbrRates;
use App\Contract\RatesProviderInterface;
use App\Dto\CbrRates\CbrRatesDto;
use App\Service\CbrRates\CbrRatesSupplier;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

readonly class CachedRatesProvider implements RatesProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
        private CbrRatesSupplier $innerProvider,
        private LoggerInterface $logger,
    ) {
    }

    public function getDailyByDate(DateTimeImmutable $date): ?CbrRatesDto
    {
        $cacheKey = sprintf(
            'CbrRatesDaily.%s',
            $date->format(CbrRates::RATE_REQUEST_DATE_FORMAT)
        );

        $this->logger->info('Attempting to get rates from cache', [
            'cache_key' => $cacheKey,
            'date' => $date->format('Y-m-d'),
        ]);

        try {
            return $this->cache->get(
                $cacheKey,
                function (ItemInterface $item) use ($date, $cacheKey): ?CbrRatesDto {
                    $this->logger->info('Cache miss, fetching from provider', [
                        'cache_key' => $cacheKey,
                    ]);

                    $item->expiresAfter(86400);

                    $result = $this->innerProvider->getDailyByDate($date);

                    if ($result) {
                        $this->logger->info('Successfully fetched and cached rates', [
                            'cache_key' => $cacheKey,
                            'rates_count' => count($result->rates),
                        ]);
                    } else {
                        $this->logger->warning('No rates returned from provider', [
                            'cache_key' => $cacheKey,
                        ]);
                    }

                    return $result;
                }
            );
        } catch (Throwable $e) {
            $this->logger->error('Cache error, falling back to direct provider call', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ]);

            return $this->innerProvider->getDailyByDate($date);
        }
    }
}
