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

readonly class CbrRatesDailyCalculator implements RateCalculatorInterface
{
    public function __construct(
        private CbrRatesDailyRepository $cbrRatesRepository,
        private CacheInterface $cache,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function calculate(RateRequestDto $requestDto): RateResponseDto
    {
        $date = DateTimeImmutable::createFromFormat(CbrRates::RATE_DATE_FORMAT, $requestDto->date);
        return $this->cache->get(
            sprintf(
                'CbrRatesDailyCalculator.%s.%s.%s',
                $date->format('Y-m-d'),
                $requestDto->code,
                $requestDto->baseCode
            ),
            function () use ($requestDto, $date): RateResponseDto {
                $rate = $this->calculateRate($date, $requestDto->code);
                $baseRate = $this->calculateRate($date, $requestDto->baseCode);

                return new RateResponseDto(
                    date: $date,
                    rate: $rate,
                    baseRate: $baseRate,
                    crossRate: new RateResponsePropertyDto(
                        code: sprintf('%s/%s', $rate->getCode(), $baseRate->getCode()),
                        value: round($rate->getValue() / $baseRate->getValue(), CbrRates::CURRENCY_VALUE_PRECISION),
                        valuePrev: round($rate->getValuePrev() / $baseRate->getValuePrev(), CbrRates::CURRENCY_VALUE_PRECISION),
                        diff: round(
                            ($rate->getValue() / $baseRate->getValue()) - ($rate->getValuePrev() / $baseRate->getValuePrev()),
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
    private function calculateRate(DateTimeImmutable $date, string $code): RateResponsePropertyDto
    {
        $rate = $this->cbrRatesRepository->findOneByDateAndCode($date, $code);
        $ratePrev = $this->cbrRatesRepository->findOneByDateAndCode($date->modify('-1 day'), $code);

        return new RateResponsePropertyDto(
            code: $rate->code,
            value: round($rate->value, CbrRates::CURRENCY_VALUE_PRECISION),
            valuePrev: round($ratePrev->value, CbrRates::CURRENCY_VALUE_PRECISION),
            diff: round($rate->value - $ratePrev->value, CbrRates::CURRENCY_VALUE_PRECISION),
        );
    }
}