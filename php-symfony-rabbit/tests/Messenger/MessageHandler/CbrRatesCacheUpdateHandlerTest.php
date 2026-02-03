<?php

namespace App\Tests\Messenger\MessageHandler;

use App\Contract\CbrRatesSupplierInterface;
use App\Dto\CbrRates\CbrRateDto;
use App\Dto\CbrRates\CbrRatesDto;
use App\Messenger\Message\CbrRatesCacheUpdateMessage;
use App\Messenger\MessageHandler\CbrRatesCacheUpdateHandler;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CbrRatesCacheUpdateHandlerTest extends TestCase
{
    /** @var MockObject&CbrRatesSupplierInterface */
    private CbrRatesSupplierInterface $supplier;

    /** @var MockObject&LoggerInterface */
    private LoggerInterface $logger;

    private CbrRatesCacheUpdateHandler $handler;

    protected function setUp(): void
    {
        $this->supplier = $this->createMock(CbrRatesSupplierInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new CbrRatesCacheUpdateHandler(
            $this->supplier,
            $this->logger
        );
    }

    /**
     * @throws Exception
     */
    public function testInvokeSuccessfullyUpdatesCache(): void
    {
        $date = new DateTimeImmutable('2023-10-25');
        $message = new CbrRatesCacheUpdateMessage($date);

        $rates = new CbrRatesDto($date, [
            new CbrRateDto('USD', 1, 75.0, 75.0),
        ]);

        $this->supplier->expects($this->once())
            ->method('getDailyByDate')
            ->with($date)
            ->willReturn($rates);

        $this->logger->expects($this->exactly(2))
            ->method('info');

        ($this->handler)($message);
    }

    /**
     * @throws Exception
     */
    public function testInvokeLogsWarningWhenNoRates(): void
    {
        $date = new DateTimeImmutable('2023-10-25');
        $message = new CbrRatesCacheUpdateMessage($date);

        $this->supplier->expects($this->once())
            ->method('getDailyByDate')
            ->with($date)
            ->willReturn(null);

        $this->logger->expects($this->once())
            ->method('info');

        $this->logger->expects($this->once())
            ->method('warning');

        ($this->handler)($message);
    }

    public function testInvokeRethrowsExceptionForRetry(): void
    {
        $date = new DateTimeImmutable('2023-10-25');
        $message = new CbrRatesCacheUpdateMessage($date);

        $exception = new Exception('Test exception');

        $this->supplier->expects($this->once())
            ->method('getDailyByDate')
            ->with($date)
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('info');

        $this->logger->expects($this->once())
            ->method('error');

        $this->expectException(Exception::class);

        ($this->handler)($message);
    }
}
