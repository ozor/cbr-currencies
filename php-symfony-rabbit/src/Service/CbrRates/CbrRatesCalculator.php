<?php

namespace App\Service\CbrRates;

use App\Contract\RateCalculatorInterface;
use App\Dto\RateRequestDto;
use App\Dto\RateResponseDto;
use App\Config\CbrRates;
use App\Dto\RateResponsePropertyDto;
use DateTimeImmutable;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;

readonly class CbrRatesCalculator implements RateCalculatorInterface
{
    public function __construct(
        private CbrRatesRepository $cbrRatesRepository,
        private CacheInterface $cache,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function calculate(RateRequestDto $requestDto): RateResponseDto
    {
        return $this->cache->get(
            sprintf(
                'CbrRatesCalculator.%s.%s.%s',
                $requestDto->date,
                $requestDto->code,
                $requestDto->baseCode
            ),
            function () use ($requestDto): RateResponseDto {
                $date = DateTimeImmutable::createFromFormat(CbrRates::RATE_DATE_FORMAT, $requestDto->date);
                $rate = $this->calculateRate($date, $requestDto->code);
                $baseRate = $this->calculateRate($date, $requestDto->baseCode);

                return new RateResponseDto(
                    date: $date,
                    rate: $rate,
                    baseRate: $baseRate,
                    crossRate: new RateResponsePropertyDto(
                        code: sprintf('%s/%s', $rate->code, $baseRate->code),
                        value: round($rate->value / $baseRate->value, CbrRates::CURRENCY_VALUE_PRECISION),
                        valuePrev: round($rate->valuePrev / $baseRate->valuePrev, CbrRates::CURRENCY_VALUE_PRECISION),
                        diff: round(
                            ($rate->value / $baseRate->value) - ($rate->valuePrev / $baseRate->valuePrev),
                            CbrRates::CURRENCY_VALUE_PRECISION
                        ),
                    ),
                );
            }
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function calculateRate(DateTimeImmutable $date, string $code): RateResponsePropertyDto
    {
        $rate = $this->cbrRatesRepository->findByDateAndCode($date, $code);
        $ratePrev = $this->cbrRatesRepository->findByDateAndCode($date->modify('-1 day'), $code);

        return new RateResponsePropertyDto(
            code: $rate->code,
            value: round($rate->value, CbrRates::CURRENCY_VALUE_PRECISION),
            valuePrev: round($ratePrev->value, CbrRates::CURRENCY_VALUE_PRECISION),
            diff: round($rate->value - $ratePrev->value, CbrRates::CURRENCY_VALUE_PRECISION),
        );
    }
}