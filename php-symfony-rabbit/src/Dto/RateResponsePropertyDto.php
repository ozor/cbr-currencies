<?php

declare(strict_types=1);

namespace App\Dto;

use App\Config\CbrRates;

final readonly class RateResponsePropertyDto
{
    public function __construct(
        public string $code,
        public float $value,
        public float $valuePrev,
        public float $diff,
    ) {
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'value' => round($this->value, CbrRates::CURRENCY_VALUE_PRECISION),
            'valuePrev' => round($this->valuePrev, CbrRates::CURRENCY_VALUE_PRECISION),
            'diff' => round($this->diff, CbrRates::CURRENCY_VALUE_PRECISION),
        ];
    }
}