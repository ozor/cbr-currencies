<?php

namespace App\Service\CbrRates;

use App\Dto\CbrRateDto;
use App\Dto\CbrRatesDto;
use App\Exception\CbrRatesExceptionInterface;
use App\Exception\RateNotFoundException;
use DateTimeImmutable;
use Exception;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;

class CbrRatesDailyRepository
{
    public function __construct(
        private readonly CbrRatesSupplier $cbrRatesSupplier,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function findOneByDateAndCode(DateTimeImmutable $date, string $code): CbrRateDto
    {
        try {
            $rates = array_filter(
                $this->findByDate($date)?->rates ?? [],
                fn(CbrRateDto $rate) => $rate->code === $code
            );
        } catch (Exception $exception) {
            if ($exception instanceof CbrRatesExceptionInterface) {
                // These types of exceptions have already caught
                throw $exception;
            }
            $this->logger->error($exception->getMessage(), [
                'exception' => $exception,
                'method' => __METHOD__,
            ]);
            throw new RateNotFoundException();
        }

        if (empty($rates)) {
            throw new RateNotFoundException();
        }

        return array_shift($rates);
    }

    public function findByDate(DateTimeImmutable $date): CbrRatesDto
    {
        $rates = $this->cbrRatesSupplier->getDailyByDate($date);
        if (empty($rates)) {
            $this->logger->error('No rates returned from supplier', [
                'method' => __METHOD__,
                'supplier' => $this->cbrRatesSupplier::class,
            ]);
            throw new RateNotFoundException();
        }

        return $rates;
    }
}