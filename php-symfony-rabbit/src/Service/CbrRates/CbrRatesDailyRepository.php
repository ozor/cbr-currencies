<?php

namespace App\Service\CbrRates;

use App\Dto\CbrRateDto;
use App\Dto\CbrRatesDto;
use App\Config\CbrRates;
use DateTimeImmutable;
use Psr\Cache\InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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