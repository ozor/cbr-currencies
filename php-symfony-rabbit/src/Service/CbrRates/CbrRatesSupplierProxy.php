<?php

namespace App\Service\CbrRates;

use App\Config\CbrRates;
use App\Contract\CbrRatesSupplierInterface;
use App\Dto\CbrRates\CbrRatesDto;
use DateTimeImmutable;
use Symfony\Contracts\Cache\CacheInterface;

readonly class CbrRatesSupplierProxy implements CbrRatesSupplierInterface
{
    public function __construct(
        private CacheInterface $cache,
        private CbrRatesSupplier $cbrRatesSupplier,
    ) {
    }

    public function getDailyByDate(DateTimeImmutable $date): ?CbrRatesDto
    {
        return $this->cache->get(
            sprintf(
                'CbrRatesDaily.%s',
                $date->format(CbrRates::RATE_REQUEST_DATE_FORMAT)
            ),
            fn (): ?CbrRatesDto => $this->cbrRatesSupplier->getDailyByDate($date)
        );
    }
}