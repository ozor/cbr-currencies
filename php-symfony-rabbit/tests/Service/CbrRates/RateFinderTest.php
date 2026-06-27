<?php

namespace App\Tests\Service\CbrRates;

use App\Dto\CbrRates\CbrRateDto;
use App\Dto\CbrRates\CbrRatesDto;
use App\Exception\CbrRates\CbrRateNotFoundException;
use App\Service\CbrRates\RateFinder;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RateFinderTest extends TestCase
{
    private RateFinder $rateFinder;
    private DateTimeImmutable $date;

    protected function setUp(): void
    {
        $this->rateFinder = new RateFinder($this->createMock(LoggerInterface::class));
        $this->date = new DateTimeImmutable('2023-10-25');
    }

    public function testFindReturnsMatchingRate(): void
    {
        $usdRate = new CbrRateDto('USD', 1, 75.0, 75.0);
        $eurRate = new CbrRateDto('EUR', 1, 85.0, 85.0);
        $snapshot = new CbrRatesDto($this->date, [$usdRate, $eurRate]);

        $result = $this->rateFinder->find($snapshot, 'USD', $this->date);

        $this->assertSame($usdRate, $result);
    }

    public function testFindReturnsSecondRate(): void
    {
        $usdRate = new CbrRateDto('USD', 1, 75.0, 75.0);
        $eurRate = new CbrRateDto('EUR', 1, 85.0, 85.0);
        $snapshot = new CbrRatesDto($this->date, [$usdRate, $eurRate]);

        $result = $this->rateFinder->find($snapshot, 'EUR', $this->date);

        $this->assertSame($eurRate, $result);
    }

    public function testFindThrowsCbrRateNotFoundExceptionWhenCodeAbsent(): void
    {
        $usdRate = new CbrRateDto('USD', 1, 75.0, 75.0);
        $snapshot = new CbrRatesDto($this->date, [$usdRate]);

        $this->expectException(CbrRateNotFoundException::class);

        $this->rateFinder->find($snapshot, 'EUR', $this->date);
    }

    public function testFindThrowsCbrRateNotFoundExceptionWhenSnapshotEmpty(): void
    {
        $snapshot = new CbrRatesDto($this->date, []);

        $this->expectException(CbrRateNotFoundException::class);

        $this->rateFinder->find($snapshot, 'USD', $this->date);
    }
}
