<?php

declare(strict_types=1);

namespace App\Dto;

use App\Config\CbrRates;
use DateTimeImmutable;

final readonly class RateResponseDto
{
    public function __construct(
        private DateTimeImmutable $date,
        private RateResponsePropertyDto $rate,
        private RateResponsePropertyDto $baseRate,
        private RateResponsePropertyDto $crossRate,
    ) {
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function getRate(): RateResponsePropertyDto
    {
        return $this->rate;
    }

    public function getBaseRate(): RateResponsePropertyDto
    {
        return $this->baseRate;
    }

    public function getCrossRate(): RateResponsePropertyDto
    {
        return $this->crossRate;
    }

    public function toArray(): array
    {
        return [
            'date' => $this->getDate()->format(CbrRates::RATE_DATE_FORMAT),
            'rate' => $this->getRate()->toArray(),
            'baseRate' => $this->getBaseRate()->toArray(),
            'crossRate' => $this->getCrossRate()->toArray(),
        ];
    }
}