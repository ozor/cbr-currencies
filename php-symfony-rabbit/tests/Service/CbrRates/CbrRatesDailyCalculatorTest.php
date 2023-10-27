<?php

namespace App\Tests\Service\CbrRates;

use App\Config\CbrRates;
use App\Dto\RateRequestDto;
use App\Dto\RateResponseDto;
use App\Dto\RateResponsePropertyDto;
use App\Service\CbrRates\CbrRatesDailyCalculator;
use App\Service\CbrRates\CbrRatesDailyRepository;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;

class CbrRatesDailyCalculatorTest extends TestCase
{
    private DateTimeImmutable $date;

    /** @var MockObject&CbrRatesDailyRepository */
    private CbrRatesDailyRepository $cbrRatesRepository;

    /** @var MockObject&CacheInterface */
    private CacheInterface $cache;

    public function setUp(): void
    {
        $this->date = new DateTimeImmutable('2023-10-25');

        $this->cbrRatesRepository = $this->createMock(CbrRatesDailyRepository::class);
        $this->cache = $this->createMock(CacheInterface::class);
    }

    public function testCalculateRateReturnsExpectedResult()
    {
        $this->cbrRatesRepository
            ->expects($this->once())
            ->method('findOneByDateAndCode')
            ->with($this->date, 'USD')
            ->willReturn(
                new RateResponsePropertyDto('USD', 75.0, 74.5, 0.5)
            );
        $this->cbrRatesRepository
            ->expects($this->once())
            ->method('findOneByDateAndCode')
            ->with($this->date, 'EUR')
            ->willReturn(
                new RateResponsePropertyDto('EUR', 85.0, 84.5, 0.5)
            );

        $this->cbrRatesRepository
            ->expects($this->once())
            ->method('findOneByDateAndCode')
            ->with($this->date->modify('-1 day'), 'USD')
            ->willReturn(
                new RateResponsePropertyDto('USD', 74.0, 73.5, 0.5)
            );
        $this->cbrRatesRepository
            ->expects($this->once())
            ->method('findOneByDateAndCode')
            ->with($this->date->modify('-1 day'), 'EUR')
            ->willReturn(
                new RateResponsePropertyDto('EUR', 84.0, 83.5, 0.5)
            );

        $this->cache
            ->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $this->cache
            ->expects($this->once())
            ->method('set');

        $calculator = new CbrRatesDailyCalculator($this->cbrRatesRepository, $this->cache);

        $requestDto = new RateRequestDto('USD', $this->date->format(CbrRates::RATE_DATE_FORMAT), 'EUR');
        $result = $calculator->calculate($requestDto);

        $expectedResult = new RateResponseDto(
            $this->date,
            new RateResponsePropertyDto('USD', 75.0, 74.5, 0.5),
            new RateResponsePropertyDto('EUR', 85.0, 84.5, 0.5),
            new RateResponsePropertyDto('USD/EUR', 0.8824, 0.8815, 0.0009)
        );

        $this->assertEquals($expectedResult, $result);
    }
}
