<?php

declare(strict_types=1);

namespace App\Dto\CbrRates;

use App\Config\CbrRates;

final readonly class CbrRateDto
{
    public function __construct(
        public string $code,
        public int $nominal,
        /**
         * Raw decimal string normalized from CBR format.
         *
         * Example: CBR "92,1234" becomes "92.1234".
         */
        public string $value,
        /**
         * Raw decimal string normalized from CBR format.
         *
         * Example: CBR "0,6123" becomes "0.6123".
         */
        public string $vunitRate,
        public string $baseCode = CbrRates::BASE_CURRENCY_CODE_DEFAULT,
    ) {
    }
}
