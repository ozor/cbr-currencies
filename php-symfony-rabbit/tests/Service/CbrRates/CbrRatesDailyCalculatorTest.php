<?php

namespace App\Tests\Service\CbrRates;

use App\Config\CbrRates;
use App\Dto\CbrRateDto;
use App\Dto\RateRequestDto;
use App\Dto\RateResponseDto;
use App\Dto\RateResponsePropertyDto;
use App\Service\CbrRates\CbrRatesDailyCalculator;
use App\Service\CbrRates\CbrRatesDailyRepository;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;

class CbrRatesDailyCalculatorTest extends TestCase
{
    private DateTimeImmutable $date;

    /** @var MockObject&CbrRatesDailyRepository */
    private CbrRatesDailyRepository $cbrRatesRepository;

    public function setUp(): void
    {
        $this->date = new DateTimeImmutable('2023-10-25');

        $this->cbrRatesRepository = $this->createMock(CbrRatesDailyRepository::class);
    }

    public function testCalculateRateReturnsExpectedResult()
    {
        $date = $this->date;
        $datePrev = $this->date->modify('-1 day');

        $requestDto = new RateRequestDto('USD', $date->format(CbrRates::RATE_DATE_FORMAT), 'EUR');

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

        $expectedResult = new RateResponseDto(
            $date,
            new RateResponsePropertyDto('USD', 75.0, 74.5, 0.5),
            new RateResponsePropertyDto('EUR', 85.0, 84.5, 0.5),
            new RateResponsePropertyDto('USD/EUR', 0.8824, 0.8817, 0.0007)
        );

        $this->assertEquals($expectedResult, $result);
    }
}
