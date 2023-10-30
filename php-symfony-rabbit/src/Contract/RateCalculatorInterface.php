<?php

namespace App\Contract;

use App\Dto\CbrRates\CbrRateRequestDto;
use App\Dto\CbrRates\CbrRateResponseDto;

interface RateCalculatorInterface
{
    public function calculate(CbrRateRequestDto $requestDto): CbrRateResponseDto;
}