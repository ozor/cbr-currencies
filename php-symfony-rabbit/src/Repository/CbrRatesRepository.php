<?php

namespace App\Repository;

use App\Contract\CbrRatesSupplierInterface;
use App\Dto\CbrRates\CbrRateDto;
use App\Dto\CbrRates\CbrRatesDto;
use App\Exception\CbrRates\CbrRateNotFoundException;
use App\Exception\CbrRates\CbrRatesExceptionInterface;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;

class CbrRatesRepository
{
    public function __construct(
        private readonly CbrRatesSupplierInterface $cbrRatesSupplier,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function findOneByDateAndCode(DateTimeImmutable $date, string $code): CbrRateDto
    {
        try {
            $ratesByDate = $this->findByDate($date)?->rates ?? [];
            $rates = array_filter(
                $ratesByDate,
                fn(CbrRateDto $rate): bool => $rate->code === $code
            );
        } catch (Exception $exception) {
            if ($exception instanceof CbrRatesExceptionInterface) {
                // These types of exceptions have already caught
                throw $exception;
            }
            $this->logger->error($exception->getMessage(), [
                'exception' => $exception,
                'method' => __METHOD__,
                'date' => $date,
                'code' => $code,
            ]);
            throw new CbrRateNotFoundException();
        }

        if (empty($rates)) {
            $this->logger->error('No any rate found in the list of rates', [
                'method' => __METHOD__,
                'date' => $date,
                'code' => $code,
                'ratesByDate' => $ratesByDate,
            ]);
            throw new CbrRateNotFoundException();
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
                'date' => $date,
            ]);
            throw new CbrRateNotFoundException();
        }

        return $rates;
    }
}