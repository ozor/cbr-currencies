<?php

namespace App\Service\CbrRates;

use App\Contract\CbrRatesSupplierInterface;
use App\Dto\CbrRatesDto;
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
            sprintf('CbrRatesDaily.%s', $date->format('Y-m-d')),
            fn (): ?CbrRatesDto => $this->cbrRatesSupplier->getDailyByDate($date)
        );
    }
}