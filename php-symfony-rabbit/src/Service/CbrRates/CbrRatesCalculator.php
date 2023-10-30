<?php

namespace App\Service\CbrRates;

use App\Config\CbrRates;
use App\Contract\CbrRatesCalculatorInterface;
use App\Dto\CbrRates\CbrRateRequestDto;
use App\Dto\CbrRates\CbrRateResponseDto;
use App\Dto\CbrRates\CbrRateResponsePropertyDto;
use App\Repository\CbrRatesRepository;
use DateTimeImmutable;

readonly class CbrRatesCalculator implements CbrRatesCalculatorInterface
{
    public function __construct(
        private CbrRatesRepository $cbrRatesRepository,
    ) {
    }

    public function calculate(CbrRateRequestDto $requestDto): CbrRateResponseDto
    {
        $date = DateTimeImmutable::createFromFormat(CbrRates::RATE_REQUEST_DATE_FORMAT, $requestDto->date)->setTime(0, 0, 0);

        $rate = $this->calculateRate($date, $requestDto->code);
        $baseRate = $this->calculateRate($date, $requestDto->baseCode);

        return new CbrRateResponseDto(
            date: $date,
            rate: $rate,
            baseRate: $baseRate,
            crossRate: new CbrRateResponsePropertyDto(
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

    private function calculateRate(DateTimeImmutable $date, string $code): CbrRateResponsePropertyDto
    {
        $rate = $this->cbrRatesRepository->findOneByDateAndCode($date, $code);
        $ratePrev = $this->cbrRatesRepository->findOneByDateAndCode($date->modify('-1 day'), $code);

        return new CbrRateResponsePropertyDto(
            code: $rate->code,
            value: round($rate->value, CbrRates::CURRENCY_VALUE_PRECISION),
            valuePrev: round($ratePrev->value, CbrRates::CURRENCY_VALUE_PRECISION),
            diff: round($rate->value - $ratePrev->value, CbrRates::CURRENCY_VALUE_PRECISION),
        );
    }
}