<?php

namespace App\Service\CbrRates;

use App\Dto\CbrRateDto;
use App\Dto\CbrRatesDto;
use DateTimeImmutable;
use Psr\Cache\InvalidArgumentException;
use RuntimeException;

class CbrRatesDailyRepository
{
    public function __construct(
        private readonly CbrRatesSupplier $cbrRatesSupplier,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function findOneByDateAndCode(DateTimeImmutable $date, string $code): CbrRateDto
    {
        $rates = array_filter(
            $this->findByDate($date)?->rates ?? [],
            fn(CbrRateDto $rate) => $rate->code === $code
        );
        if (empty($rates)) {
            throw new RuntimeException('Rate not found');
        }

        return array_shift($rates);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function findByDate(DateTimeImmutable $date): CbrRatesDto
    {
        $rates = $this->cbrRatesSupplier->getDailyByDate($date);
        if (empty($rates)) {
            throw new RuntimeException('Rates not found');
        }

        return $rates;
    }
}