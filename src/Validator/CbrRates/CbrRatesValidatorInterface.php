<?php

namespace App\Validator\CbrRates;

use App\Dto\CbrRates\CbrRateRequestDto;

interface CbrRatesValidatorInterface
{
    public function validate(CbrRateRequestDto $dto);
}