<?php

declare(strict_types=1);

namespace App\Dto;

use App\Config\CbrRates;

final readonly class CbrRateDto
{
    public function __construct(
        public string $code,
        public int $nominal,
        public float $value,
        public float $vunitRate,
        public string $baseCode = CbrRates::BASE_CURRENCY_CODE_DEFAULT,
    ) {
    }
}