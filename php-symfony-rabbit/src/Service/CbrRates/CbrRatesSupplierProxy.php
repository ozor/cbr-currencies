<?php

namespace App\Service\CbrRates;

use App\Config\CbrRates;
use App\Contract\CbrRatesSupplierInterface;
use App\Dto\CbrRates\CbrRatesDto;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

readonly class CbrRatesSupplierProxy implements CbrRatesSupplierInterface
{
    public function __construct(
        private CacheInterface $cache,
        private CbrRatesSupplier $cbrRatesSupplier,
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
                    $this->logger->info('Cache miss, fetching from supplier', [
                        'cache_key' => $cacheKey,
                    ]);

                    // Кешируем на 24 часа
                    $item->expiresAfter(86400);

                    $result = $this->cbrRatesSupplier->getDailyByDate($date);

                    if ($result) {
                        $this->logger->info('Successfully fetched and cached rates', [
                            'cache_key' => $cacheKey,
                            'rates_count' => count($result->rates),
                        ]);
                    } else {
                        $this->logger->warning('No rates returned from supplier', [
                            'cache_key' => $cacheKey,
                        ]);
                    }

                    return $result;
                }
            );
        } catch (Throwable $e) {
            $this->logger->error('Cache error, falling back to direct supplier call', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ]);

            // В случае ошибки кеша - обращаемся напрямую
            return $this->cbrRatesSupplier->getDailyByDate($date);
        }
    }
}
