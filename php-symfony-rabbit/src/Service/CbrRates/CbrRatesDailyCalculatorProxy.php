<?php

namespace App\Service\CbrRates;

use App\Contract\RateCalculatorInterface;
use App\Dto\RateRequestDto;
use App\Dto\RateResponseDto;
use Symfony\Contracts\Cache\CacheInterface;

readonly class CbrRatesDailyCalculatorProxy implements RateCalculatorInterface
{
    public function __construct(
        private CacheInterface $cache,
        private CbrRatesDailyCalculator $cbrRatesDailyCalculator,
    ) {
    }

    public function calculate(RateRequestDto $requestDto): RateResponseDto
    {
        return $this->cache->get(
            sprintf(
                'CbrRatesDailyCalculator.%s.%s.%s',
                str_replace('/', '-', $requestDto->date),
                $requestDto->code,
                $requestDto->baseCode
            ),
            fn (): RateResponseDto => $this->cbrRatesDailyCalculator->calculate($requestDto)
        );
    }
}
