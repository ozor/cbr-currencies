<?php

namespace App\Tests\Service\CbrRates;

use App\Config\CbrRates;
use App\Contract\RatesProviderInterface;
use App\Domain\Calendar\PreviousTradingDayResolver;
use App\Dto\CbrRates\CbrRateDto;
use App\Dto\CbrRates\CbrRateRequestDto;
use App\Dto\CbrRates\CbrRateResponseDto;
use App\Dto\CbrRates\CbrRateResponsePropertyDto;
use App\Dto\CbrRates\CbrRatesDto;
use App\Exception\CbrRates\RateNotFoundException;
use App\Exception\CbrRates\PreviousTradingDayNotFoundException;
use App\Service\CbrRates\CbrRatesCalculator;
use App\Service\CbrRates\RateFinder;
use DateMalformedStringException;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CbrRatesDailyCalculatorTest extends TestCase
{
    private DateTimeImmutable $date;
    private DateTimeImmutable $datePrev;

    /** @var MockObject&RatesProviderInterface */
    private RatesProviderInterface $ratesProvider;

    /** @var MockObject&PreviousTradingDayResolver */
    private PreviousTradingDayResolver $previousTradingDayResolver;

    private RateFinder $rateFinder;

    /**
     * @throws DateMalformedStringException
     */
    public function setUp(): void
    {
        $this->date = new DateTimeImmutable('2023-10-25');
        $this->datePrev = new DateTimeImmutable('2023-10-24');

        $this->ratesProvider = $this->createMock(RatesProviderInterface::class);
        $this->previousTradingDayResolver = $this->createMock(PreviousTradingDayResolver::class);
        $this->rateFinder = new RateFinder($this->createMock(LoggerInterface::class));
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testCalculateRateReturnsExpectedResult(): void
    {
        $date = $this->date;
        $datePrev = $this->datePrev;

        $requestDto = new CbrRateRequestDto($date->format(CbrRates::RATE_REQUEST_DATE_FORMAT), 'USD', 'EUR');

        $snapshotCurrent = new CbrRatesDto($date, [
            new CbrRateDto('USD', 1, 75.0, 75.0),
            new CbrRateDto('EUR', 1, 85.0, 85.0),
        ]);

        $snapshotPrev = new CbrRatesDto($datePrev, [
            new CbrRateDto('USD', 1, 74.5, 74.5),
            new CbrRateDto('EUR', 1, 84.5, 84.5),
        ]);

        $this->previousTradingDayResolver
            ->expects($this->exactly(2))
            ->method('resolve')
            ->with($this->callback(fn (DateTimeImmutable $d) => $d->format('Y-m-d') === $date->format('Y-m-d')))
            ->willReturn($datePrev);

        $this->ratesProvider
            ->expects($this->exactly(4))
            ->method('getDailyByDate')
            ->willReturnCallback(
                function (DateTimeImmutable $d) use ($date, $datePrev, $snapshotCurrent, $snapshotPrev): CbrRatesDto {
                    if ($d->format('Y-m-d') === $date->format('Y-m-d')) {
                        return $snapshotCurrent;
                    }
                    return $snapshotPrev;
                }
            );

        $calculator = new CbrRatesCalculator($this->ratesProvider, $this->rateFinder, $this->previousTradingDayResolver);

        $result = $calculator->calculate($requestDto);

        $expectedResult = new CbrRateResponseDto(
            $date,
            new CbrRateResponsePropertyDto('USD', 75.0, 74.5, 0.5),
            new CbrRateResponsePropertyDto('EUR', 85.0, 84.5, 0.5),
            new CbrRateResponsePropertyDto('USD/EUR', 0.8824, 0.8817, 0.0007)
        );

        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testCalculateThrowsWhenSnapshotIsNull(): void
    {
        $date = $this->date;
        $requestDto = new CbrRateRequestDto($date->format(
            CbrRates::RATE_REQUEST_DATE_FORMAT),
            'USD',
            'EUR'
        );

        $this->previousTradingDayResolver
            ->method('resolve')
            ->willReturn($this->datePrev);

        $this->ratesProvider
            ->method('getDailyByDate')
            ->willReturn(null);

        $calculator = new CbrRatesCalculator($this->ratesProvider, $this->rateFinder, $this->previousTradingDayResolver);

        $this->expectException(RateNotFoundException::class);

        $calculator->calculate($requestDto);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function testCalculateThrowsWhenResolverCannotFindPreviousTradingDay(): void
    {
        $date = $this->date;
        $requestDto = new CbrRateRequestDto($date->format(
            CbrRates::RATE_REQUEST_DATE_FORMAT),
            'USD',
            'EUR'
        );

        $this->previousTradingDayResolver
            ->method('resolve')
            ->willThrowException(new PreviousTradingDayNotFoundException());

        $this->ratesProvider
            ->method('getDailyByDate')
            ->willReturn(new CbrRatesDto($date, []));

        $calculator = new CbrRatesCalculator($this->ratesProvider, $this->rateFinder, $this->previousTradingDayResolver);

        $this->expectException(PreviousTradingDayNotFoundException::class);

        $calculator->calculate($requestDto);
    }
}
