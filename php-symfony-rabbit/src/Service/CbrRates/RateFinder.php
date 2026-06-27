<?php

namespace App\Service\CbrRates;

use App\Dto\CbrRates\CbrRateDto;
use App\Dto\CbrRates\CbrRatesDto;
use App\Exception\CbrRates\CbrRateNotFoundException;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

readonly class RateFinder
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws CbrRateNotFoundException
     */
    public function find(CbrRatesDto $snapshot, string $code, DateTimeImmutable $date): CbrRateDto
    {
        $rates = array_filter(
            $snapshot->rates,
            fn(CbrRateDto $rate): bool => $rate->code === $code
        );

        if (empty($rates)) {
            $this->logger->error('Rate not found in snapshot', [
                'method' => __METHOD__,
                'code' => $code,
                'date' => $date->format('Y-m-d'),
            ]);
            throw new CbrRateNotFoundException();
        }

        return array_shift($rates);
    }
}
