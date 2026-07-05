<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\CbrRates;

use App\Config\CbrRates;
use App\Contract\RatesProviderInterface;
use App\Domain\Calendar\PreviousTradingDayResolver;
use App\Dto\CbrRates\CbrRateDto;
use App\Dto\CbrRates\CbrRateRequestDto;
use App\Dto\CbrRates\CbrRateResponseDto;
use App\Dto\CbrRates\CbrRateResponsePropertyDto;
use App\Dto\CbrRates\CbrRatesDto;
use App\Exception\CbrRates\PreviousTradingDayNotFoundException;
use App\Exception\CbrRates\RateNotFoundException;
use App\Service\CbrRates\CbrRatesCalculator;
use App\Service\CbrRates\RateFinder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CbrRatesDailyCalculatorTest extends TestCase
{
    private \DateTimeImmutable $date;
    private \DateTimeImmutable $datePrev;

    /** @var MockObject&RatesProviderInterface */
    private RatesProviderInterface $ratesProvider;

    private PreviousTradingDayResolver $previousTradingDayResolver;

    private RateFinder $rateFinder;

    /**
     * @throws \DateMalformedStringException
     */
    public function setUp(): void
    {
        $this->date = new \DateTimeImmutable('2023-10-25');
        $this->datePrev = new \DateTimeImmutable('2023-10-24');

        $this->ratesProvider = $this->createMock(RatesProviderInterface::class);
        // Use a real resolver backed by the mocked rates provider so tests don't mock the readonly class.
        $this->previousTradingDayResolver = new PreviousTradingDayResolver($this->ratesProvider);
        $this->rateFinder = new RateFinder($this->createMock(LoggerInterface::class));
    }

    /**
     * @throws \DateMalformedStringException
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

        // Set up rates provider behavior: current date -> current snapshot, previous date -> prev snapshot
        $this->ratesProvider
            ->method('getDailyByDate')
            ->willReturnCallback(
                function (\DateTimeImmutable $d) use ($date, $snapshotCurrent, $snapshotPrev): CbrRatesDto {
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
     * @throws \DateMalformedStringException
     */
    public function testCalculateThrowsWhenSnapshotIsNull(): void
    {
        $date = $this->date;
        $requestDto = new CbrRateRequestDto(
            $date->format(CbrRates::RATE_REQUEST_DATE_FORMAT),
            'USD',
            'EUR'
        );

        $datePrev = $this->datePrev;

        // Make resolver find $datePrev by returning non-null for the previous date, but ensure
        // the snapshot for the main date is null so the calculator throws RateNotFoundException.
        $this->ratesProvider
            ->method('getDailyByDate')
            ->willReturnCallback(function (\DateTimeImmutable $d) use ($date, $datePrev) {
                if ($d->format('Y-m-d') === $date->format('Y-m-d')) {
                    return null; // main date missing
                }

                if ($d->format('Y-m-d') === $datePrev->format('Y-m-d')) {
                    return new CbrRatesDto($datePrev, []);
                }

                return null;
            });

        $calculator = new CbrRatesCalculator($this->ratesProvider, $this->rateFinder, $this->previousTradingDayResolver);

        $this->expectException(RateNotFoundException::class);

        $calculator->calculate($requestDto);
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function testCalculateThrowsWhenResolverCannotFindPreviousTradingDay(): void
    {
        $date = $this->date;
        $requestDto = new CbrRateRequestDto(
            $date->format(CbrRates::RATE_REQUEST_DATE_FORMAT),
            'USD',
            'EUR'
        );

        // When ratesProvider returns null for all previous days, resolver will throw
        $this->ratesProvider
            ->method('getDailyByDate')
            ->willReturn(null);

        $calculator = new CbrRatesCalculator($this->ratesProvider, $this->rateFinder, $this->previousTradingDayResolver);

        $this->expectException(PreviousTradingDayNotFoundException::class);

        $calculator->calculate($requestDto);
    }
}
