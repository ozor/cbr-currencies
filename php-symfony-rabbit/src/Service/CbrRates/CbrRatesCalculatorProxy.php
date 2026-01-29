<?php

namespace App\Service\CbrRates;

use App\Contract\CbrRatesCalculatorInterface;
use App\Dto\CbrRates\CbrRateRequestDto;
use App\Dto\CbrRates\CbrRateResponseDto;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

readonly class CbrRatesCalculatorProxy implements CbrRatesCalculatorInterface
{
    public function __construct(
        private CacheInterface $cache,
        private CbrRatesCalculator $cbrRatesDailyCalculator,
        private LoggerInterface $logger,
    ) {
    }

    public function calculate(CbrRateRequestDto $requestDto): CbrRateResponseDto
    {
        $cacheKey = sprintf(
            'CbrRatesDailyCalculator.%s.%s.%s',
            $requestDto->date,
            $requestDto->code,
            $requestDto->baseCode
        );

        $this->logger->info('Attempting to calculate rates from cache', [
            'cache_key' => $cacheKey,
            'date' => $requestDto->date,
            'code' => $requestDto->code,
            'base_code' => $requestDto->baseCode,
        ]);

        try {
            return $this->cache->get(
                $cacheKey,
                function (ItemInterface $item) use ($requestDto, $cacheKey): CbrRateResponseDto {
                    $this->logger->info('Cache miss, calculating rates', [
                        'cache_key' => $cacheKey,
                    ]);

                    // Кешируем на 24 часа
                    $item->expiresAfter(86400);

                    $result = $this->cbrRatesDailyCalculator->calculate($requestDto);

                    $this->logger->info('Successfully calculated and cached rates', [
                        'cache_key' => $cacheKey,
                        'rate_code' => $result->getRate()->getCode(),
                        'base_rate_code' => $result->getBaseRate()->getCode(),
                    ]);

                    return $result;
                }
            );
        } catch (Throwable $e) {
            $this->logger->error('Cache error, falling back to direct calculator call', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ]);

            // В случае ошибки кеша - обращаемся напрямую
            return $this->cbrRatesDailyCalculator->calculate($requestDto);
        }
    }
}
