<?php

namespace App\Service\CbrRates;

use App\Dto\CbrRateDto;
use App\Dto\CbrRatesDto;
use DateTimeImmutable;
use RuntimeException;

class CbrRatesDailyRepository
{
    public function __construct(
        private readonly CbrRatesSupplier $cbrRatesSupplier,
    ) {
    }

    public function findByDateAndCode(DateTimeImmutable $date, string $code): CbrRateDto
    {
        $rates = array_filter(
            $this->findByDate($date)?->rates ?? [],
            fn(CbrRateDto $rate) => $rate->code === $code
        );
        if (empty($rates)) {
            throw new RuntimeException('Rates not found');
        }

        return array_shift($rates);
    }

    public function findByDate(DateTimeImmutable $date): CbrRatesDto
    {
        $rates = ($this->cbrRatesSupplier)($date);
        if (empty($rates)) {
            throw new RuntimeException('Rates not found');
        }

        return $rates;
    }
}