<?php

declare(strict_types=1);

namespace App\Dto;

use App\Config\CbrRates;
use DateTimeImmutable;

final readonly class RateResponseDto
{
    public function __construct(
        public DateTimeImmutable $date,
        public RateResponsePropertyDto $rate,
        public RateResponsePropertyDto $baseRate,
        public RateResponsePropertyDto $crossRate,
    ) {
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date->format(CbrRates::RATE_DATE_FORMAT),
            'rate' => $this->rate->toArray(),
            'baseRate' => $this->baseRate->toArray(),
            'crossRate' => $this->crossRate->toArray(),
        ];
    }
}