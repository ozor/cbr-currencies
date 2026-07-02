<?php

declare(strict_types=1);

namespace App\Dto\CbrRates;

use App\Config\CbrRates;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CbrRateRequestDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Date should not be blank.')]
        #[Assert\DateTime(
            format: CbrRates::RATE_REQUEST_DATE_FORMAT,
            message: 'Wrong date format.'
        )]
        public string $date,
        #[Assert\NotBlank(message: 'Currency code should not be blank.')]
        #[Assert\Currency()]
        public string $code,
        #[Assert\Currency()]
        public string $baseCode = CbrRates::BASE_CURRENCY_CODE_DEFAULT,
    ) {
    }
}