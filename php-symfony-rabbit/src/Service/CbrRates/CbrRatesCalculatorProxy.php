<?php

namespace App\Service\CbrRates;

use App\Contract\CbrRatesCalculatorInterface;
use App\Dto\CbrRates\CbrRateRequestDto;
use App\Dto\CbrRates\CbrRateResponseDto;
use Symfony\Contracts\Cache\CacheInterface;

readonly class CbrRatesCalculatorProxy implements CbrRatesCalculatorInterface
{
    public function __construct(
        private CacheInterface     $cache,
        private CbrRatesCalculator $cbrRatesDailyCalculator,
    ) {
    }

    public function calculate(CbrRateRequestDto $requestDto): CbrRateResponseDto
    {
        return $this->cache->get(
            sprintf(
                'CbrRatesDailyCalculator.%s.%s.%s',
                $requestDto->date,
                $requestDto->code,
                $requestDto->baseCode
            ),
            fn (): CbrRateResponseDto => $this->cbrRatesDailyCalculator->calculate($requestDto)
        );
    }
}
