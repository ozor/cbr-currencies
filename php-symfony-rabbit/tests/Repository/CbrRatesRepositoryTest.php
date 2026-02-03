<?php

namespace App\Tests\Repository;

use App\Contract\CbrRatesSupplierInterface;
use App\Dto\CbrRates\CbrRateDto;
use App\Dto\CbrRates\CbrRatesDto;
use App\Exception\CbrRates\CbrRateNotFoundException;
use App\Repository\CbrRatesRepository;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CbrRatesRepositoryTest extends TestCase
{
    /** @var MockObject&CbrRatesSupplierInterface */
    private CbrRatesSupplierInterface $supplier;

    private CbrRatesRepository $repository;

    protected function setUp(): void
    {
        $this->supplier = $this->createMock(CbrRatesSupplierInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $this->repository = new CbrRatesRepository(
            $this->supplier,
            $logger
        );
    }

    public function testFindOneByDateAndCodeReturnsRate(): void
    {
        $date = new DateTimeImmutable('2023-10-25');
        $usdRate = new CbrRateDto('USD', 1, 75.0, 75.0);
        $eurRate = new CbrRateDto('EUR', 1, 85.0, 85.0);

        $rates = new CbrRatesDto($date, [$usdRate, $eurRate]);

        $this->supplier->expects($this->once())
            ->method('getDailyByDate')
            ->with($date)
            ->willReturn($rates);

        $result = $this->repository->findOneByDateAndCode($date, 'USD');

        $this->assertSame($usdRate, $result);
    }

    public function testFindOneByDateAndCodeThrowsExceptionWhenRateNotFound(): void
    {
        $date = new DateTimeImmutable('2023-10-25');
        $usdRate = new CbrRateDto('USD', 1, 75.0, 75.0);

        $rates = new CbrRatesDto($date, [$usdRate]);

        $this->supplier->expects($this->once())
            ->method('getDailyByDate')
            ->with($date)
            ->willReturn($rates);

        $this->expectException(CbrRateNotFoundException::class);

        $this->repository->findOneByDateAndCode($date, 'EUR');
    }

    public function testFindByDateReturnsRates(): void
    {
        $date = new DateTimeImmutable('2023-10-25');
        $rates = new CbrRatesDto($date, [
            new CbrRateDto('USD', 1, 75.0, 75.0),
        ]);

        $this->supplier->expects($this->once())
            ->method('getDailyByDate')
            ->with($date)
            ->willReturn($rates);

        $result = $this->repository->findByDate($date);

        $this->assertSame($rates, $result);
    }

    public function testFindByDateThrowsExceptionWhenNoRates(): void
    {
        $date = new DateTimeImmutable('2023-10-25');

        $this->supplier->expects($this->once())
            ->method('getDailyByDate')
            ->with($date)
            ->willReturn(null);

        $this->expectException(CbrRateNotFoundException::class);

        $this->repository->findByDate($date);
    }
}
