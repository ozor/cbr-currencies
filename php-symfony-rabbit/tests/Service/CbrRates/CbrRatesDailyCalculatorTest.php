<?php

namespace App\Tests\Service\CbrRates;

use App\Config\CbrRates;
use App\Dto\CbrRates\CbrRateDto;
use App\Dto\CbrRates\CbrRateRequestDto;
use App\Dto\CbrRates\CbrRateResponseDto;
use App\Dto\CbrRates\CbrRateResponsePropertyDto;
use App\Repository\CbrRatesRepository;
use App\Service\CbrRates\CbrRatesDailyCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CbrRatesDailyCalculatorTest extends TestCase
{
    private DateTimeImmutable $date;

    /** @var MockObject&CbrRatesRepository */
    private CbrRatesRepository $cbrRatesRepository;

    public function setUp(): void
    {
        $this->date = new DateTimeImmutable('2023-10-25');

        $this->cbrRatesRepository = $this->createMock(CbrRatesRepository::class);
    }

    public function testCalculateRateReturnsExpectedResult()
    {
        $date = $this->date;
        $datePrev = $this->date->modify('-1 day');

        $requestDto = new CbrRateRequestDto($date->format(CbrRates::RATE_REQUEST_DATE_FORMAT), 'USD', 'EUR');

        $this->cbrRatesRepository
            ->expects($this->exactly(4))
            ->method('findOneByDateAndCode')
//            ->with($date, 'USD')
            ->willReturn(
                new CbrRateDto('USD', 1, 75.0, 75.0),
                new CbrRateDto('USD', 1, 74.5, 74.5),
                new CbrRateDto('EUR', 1, 85.0, 85.0),
                new CbrRateDto('EUR', 1, 84.5, 84.5)
            );

        $calculator = new CbrRatesDailyCalculator($this->cbrRatesRepository);

        $result = $calculator->calculate($requestDto);

        $expectedResult = new CbrRateResponseDto(
            $date,
            new CbrRateResponsePropertyDto('USD', 75.0, 74.5, 0.5),
            new CbrRateResponsePropertyDto('EUR', 85.0, 84.5, 0.5),
            new CbrRateResponsePropertyDto('USD/EUR', 0.8824, 0.8817, 0.0007)
        );

        $this->assertEquals($expectedResult, $result);
    }
}
