<?php

declare(strict_types=1);

namespace App\Dto\CbrRates;

use App\Config\CbrRates;
use DateTimeImmutable;

final readonly class CbrRateResponseDto
{
    public function __construct(
        private DateTimeImmutable          $date,
        private CbrRateResponsePropertyDto $rate,
        private CbrRateResponsePropertyDto $baseRate,
        private CbrRateResponsePropertyDto $crossRate,
    ) {
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function getRate(): CbrRateResponsePropertyDto
    {
        return $this->rate;
    }

    public function getBaseRate(): CbrRateResponsePropertyDto
    {
        return $this->baseRate;
    }

    public function getCrossRate(): CbrRateResponsePropertyDto
    {
        return $this->crossRate;
    }

    public function toArray(): array
    {
        return [
            'date' => $this->getDate()->format(CbrRates::RATE_REQUEST_DATE_FORMAT),
            'rate' => $this->getRate()->toArray(),
            'baseRate' => $this->getBaseRate()->toArray(),
            'crossRate' => $this->getCrossRate()->toArray(),
        ];
    }
}