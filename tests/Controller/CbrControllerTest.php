<?php

namespace App\Tests\Controller;

use App\Dto\CbrRates\CbrRateRequestDto;
use App\Dto\CbrRates\CbrRateResponseDto;
use App\Dto\CbrRates\CbrRateResponsePropertyDto;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class CbrControllerTest extends TestCase
{
    public function testRatesValidatesRequestAndCalculates(): void
    {
        $date = '25.10.2023';
        $code = 'USD';
        $baseCode = 'EUR';

        $requestDto = new CbrRateRequestDto($date, $code, $baseCode);

        $this->assertEquals($date, $requestDto->date);
        $this->assertEquals($code, $requestDto->code);
        $this->assertEquals($baseCode, $requestDto->baseCode);

        $responseDto = new CbrRateResponseDto(
            new DateTimeImmutable('2023-10-25'),
            new CbrRateResponsePropertyDto('USD', 75.0, 74.5, 0.5),
            new CbrRateResponsePropertyDto('EUR', 85.0, 84.5, 0.5),
            new CbrRateResponsePropertyDto('USD/EUR', 0.8824, 0.8817, 0.0007)
        );

        $this->assertEquals('USD', $responseDto->getRate()->getCode());
        $this->assertEquals('EUR', $responseDto->getBaseRate()->getCode());
    }
}
