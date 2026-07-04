<?php

declare(strict_types=1);

namespace App\Service\CbrRates;

use App\Config\CbrRates;
use App\Contract\CbrRatesCalculatorInterface;
use App\Contract\RatesProviderInterface;
use App\Domain\Calendar\PreviousTradingDayResolver;
use App\Dto\CbrRates\CbrRateRequestDto;
use App\Dto\CbrRates\CbrRateResponseDto;
use App\Dto\CbrRates\CbrRateResponsePropertyDto;
use App\Exception\CbrRates\RateNotFoundException;
use DateMalformedStringException;
use DateTimeImmutable;
use UnexpectedValueException;

readonly class CbrRatesCalculator implements CbrRatesCalculatorInterface
{
    public function __construct(
        private RatesProviderInterface $ratesProvider,
        private RateFinder $rateFinder,
        private PreviousTradingDayResolver $previousTradingDayResolver,
    ) {
    }

    /**
     * @throws DateMalformedStringException
     */
    public function calculate(CbrRateRequestDto $requestDto): CbrRateResponseDto
    {
        $parsed = DateTimeImmutable::createFromFormat(
            CbrRates::RATE_REQUEST_DATE_FORMAT,
            $requestDto->date,
        );

        // Date is already validated by CbrRatesValidator; false is unreachable in normal flow.
        if ($parsed === false) {
            throw new UnexpectedValueException(sprintf('Invalid date format: %s', $requestDto->date));
        }

        $date = $parsed->setTime(0, 0);

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
        $datePrev = $this->previousTradingDayResolver->resolve($date);

        $snapshot = $this->ratesProvider->getDailyByDate($date);
        if ($snapshot === null) {
            throw new RateNotFoundException();
        }

        $snapshotPrev = $this->ratesProvider->getDailyByDate($datePrev);
        if ($snapshotPrev === null) {
            throw new RateNotFoundException();
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
