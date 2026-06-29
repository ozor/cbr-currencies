<?php

namespace App\Tests\Domain\Calendar;

use App\Contract\RatesProviderInterface;
use App\Domain\Calendar\PreviousTradingDayResolver;
use App\Dto\CbrRates\CbrRatesDto;
use App\Exception\CbrRates\PreviousTradingDayNotFoundException;
use DateMalformedStringException;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PreviousTradingDayResolverTest extends TestCase
{
    /** @var MockObject&RatesProviderInterface */
    private RatesProviderInterface $ratesProvider;

    private PreviousTradingDayResolver $resolver;

    protected function setUp(): void
    {
        $this->ratesProvider = $this->createMock(RatesProviderInterface::class);
        $this->resolver = new PreviousTradingDayResolver($this->ratesProvider);
    }

    /**
     * Tuesday 2025-01-07 → Monday 2025-01-06 (snapshot available on first try).
     * @throws DateMalformedStringException
     */
    public function testResolvesFirstAvailablePreviousDay(): void
    {
        $tuesday = new DateTimeImmutable('2025-01-07'); // Tuesday
        $monday  = new DateTimeImmutable('2025-01-06'); // Monday

        $snapshot = $this->createStub(CbrRatesDto::class);

        $this->ratesProvider
            ->expects($this->once())
            ->method('getDailyByDate')
            ->with($this->callback(fn (DateTimeImmutable $d) => $d->format('Y-m-d') === $monday->format('Y-m-d')))
            ->willReturn($snapshot);

        $result = $this->resolver->resolve($tuesday);

        $this->assertSame($monday->format('Y-m-d'), $result->format('Y-m-d'));
    }

    /**
     * Monday 2025-01-06 → Sunday null, Saturday null → Friday 2025-01-03 (3rd attempt).
     * @throws DateMalformedStringException
     */
    public function testSkipsUnavailableDatesAndFindsFriday(): void
    {
        $monday  = new DateTimeImmutable('2025-01-06'); // Monday
        $sunday  = new DateTimeImmutable('2025-01-05'); // Sunday  – no snapshot
        $saturday = new DateTimeImmutable('2025-01-04'); // Saturday – no snapshot
        $friday  = new DateTimeImmutable('2025-01-03'); // Friday   – snapshot present

        $snapshot = $this->createStub(CbrRatesDto::class);

        $this->ratesProvider
            ->expects($this->exactly(3))
            ->method('getDailyByDate')
            ->willReturnCallback(function (DateTimeImmutable $d) use ($sunday, $saturday, $friday, $snapshot): ?CbrRatesDto {
                return match ($d->format('Y-m-d')) {
                    $sunday->format('Y-m-d')   => null,
                    $saturday->format('Y-m-d') => null,
                    $friday->format('Y-m-d')   => $snapshot,
                    default                    => null,
                };
            });

        $result = $this->resolver->resolve($monday);

        $this->assertSame($friday->format('Y-m-d'), $result->format('Y-m-d'));
    }

    /**
     * When multiple consecutive days have no snapshot but one is found before the limit.
     * @throws DateMalformedStringException
     */
    public function testReturnsFirstFoundDateWithinLimit(): void
    {
        $date     = new DateTimeImmutable('2025-01-10');
        $snapshot = $this->createStub(CbrRatesDto::class);

        // First 5 days null, 6th day has snapshot
        $this->ratesProvider
            ->expects($this->exactly(6))
            ->method('getDailyByDate')
            ->willReturnOnConsecutiveCalls(null, null, null, null, null, $snapshot);

        $expected = $date->modify('-6 day')->format('Y-m-d');
        $result   = $this->resolver->resolve($date);

        $this->assertSame($expected, $result->format('Y-m-d'));
    }

    /**
     * All 15 attempts return null → PreviousTradingDayNotFoundException must be thrown.
     * @throws DateMalformedStringException
     */
    public function testThrowsWhenNoSnapshotFoundWithinLimit(): void
    {
        $date = new DateTimeImmutable('2025-01-10');

        $this->ratesProvider
            ->expects($this->exactly(15))
            ->method('getDailyByDate')
            ->willReturn(null);

        $this->expectException(PreviousTradingDayNotFoundException::class);

        $this->resolver->resolve($date);
    }
}
