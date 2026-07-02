<?php

namespace App\Contract;

use App\Dto\CbrRates\CbrRateRequestDto;
use App\Dto\CbrRates\CbrRateResponseDto;

interface CbrRatesCalculatorInterface
{
    public function calculate(CbrRateRequestDto $requestDto): CbrRateResponseDto;
}