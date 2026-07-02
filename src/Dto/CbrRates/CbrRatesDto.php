<?php

declare(strict_types=1);

namespace App\Dto\CbrRates;

use DateTimeImmutable;

final readonly class CbrRatesDto
{
    /**
     * @param CbrRateDto[] $rates
     */
    public function __construct(
        public DateTimeImmutable $tradingDate,
        public array $rates
    ) {
    }
}