<?php

namespace App\Contract;

use App\Dto\RateRequestDto;
use App\Dto\RateResponseDto;

interface RateCalculatorInterface
{
    public function calculate(RateRequestDto $requestDto): RateResponseDto;
}