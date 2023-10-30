<?php

namespace App\Service\CbrRates;

use App\Contract\RateCalculatorInterface;
use App\Dto\CbrRates\CbrRateRequestDto;
use App\Dto\CbrRates\CbrRateResponseDto;
use Symfony\Contracts\Cache\CacheInterface;

readonly class CbrRatesDailyCalculatorProxy implements RateCalculatorInterface
{
    public function __construct(
        private CacheInterface $cache,
        private CbrRatesDailyCalculator $cbrRatesDailyCalculator,
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
