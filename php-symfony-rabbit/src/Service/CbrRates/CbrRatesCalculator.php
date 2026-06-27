<?php

namespace App\Service\CbrRates;

use App\Config\CbrRates;
use App\Contract\CbrRatesCalculatorInterface;
use App\Contract\RatesProviderInterface;
use App\Dto\CbrRates\CbrRateRequestDto;
use App\Dto\CbrRates\CbrRateResponseDto;
use App\Dto\CbrRates\CbrRateResponsePropertyDto;
use App\Exception\CbrRates\CbrRateNotFoundException;
use DateMalformedStringException;
use DateTimeImmutable;

readonly class CbrRatesCalculator implements CbrRatesCalculatorInterface
{
    public function __construct(
        private RatesProviderInterface $ratesProvider,
        private RateFinder $rateFinder,
    ) {
    }

    /**
     * @throws DateMalformedStringException
     */
    public function calculate(CbrRateRequestDto $requestDto): CbrRateResponseDto
    {
        $date = DateTimeImmutable::createFromFormat(
            CbrRates::RATE_REQUEST_DATE_FORMAT,
            $requestDto->date,
        )->setTime(0, 0, 0);

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

    /**
     * @throws DateMalformedStringException
     */
    private function calculateRate(DateTimeImmutable $date, string $code): CbrRateResponsePropertyDto
    {
        $datePrev = $date->modify('-1 day');

        $snapshot = $this->ratesProvider->getDailyByDate($date);
        if ($snapshot === null) {
            throw new CbrRateNotFoundException();
        }

        $snapshotPrev = $this->ratesProvider->getDailyByDate($datePrev);
        if ($snapshotPrev === null) {
            throw new CbrRateNotFoundException();
        }

        $rate = $this->rateFinder->find($snapshot, $code, $date);
        $ratePrev = $this->rateFinder->find($snapshotPrev, $code, $datePrev);

        return new CbrRateResponsePropertyDto(
            code: $rate->code,
            value: round($rate->value, CbrRates::CURRENCY_VALUE_PRECISION),
            valuePrev: round($ratePrev->value, CbrRates::CURRENCY_VALUE_PRECISION),
            diff: round($rate->value - $ratePrev->value, CbrRates::CURRENCY_VALUE_PRECISION),
        );
    }
}