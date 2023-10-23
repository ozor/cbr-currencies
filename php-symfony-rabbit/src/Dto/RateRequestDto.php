<?php

declare(strict_types=1);

namespace App\Dto;

use App\Config\CbrRates;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class RateRequestDto
{
    public function __construct(
        #[Assert\Length(exactly: CbrRates::CURRENCY_CODE_LENGTH)]
        public string $code,
        #[Assert\DateTime(format: CbrRates::RATE_DATE_FORMAT)]
        public string $date,
        #[Assert\Length(exactly: CbrRates::CURRENCY_CODE_LENGTH)]
        public string $baseCode = CbrRates::BASE_CURRENCY_CODE_DEFAULT,
    ) {
    }
}