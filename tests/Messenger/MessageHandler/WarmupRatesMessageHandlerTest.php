<?php

namespace App\Tests\Messenger\MessageHandler;

use App\Contract\RatesProviderInterface;
use App\Dto\CbrRates\CbrRateDto;
use App\Dto\CbrRates\CbrRatesDto;
use App\Messenger\Message\WarmupRatesMessage;
use App\Messenger\MessageHandler\WarmupRatesMessageHandler;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WarmupRatesMessageHandlerTest extends TestCase
{
    /** @var MockObject&RatesProviderInterface */
    private RatesProviderInterface $ratesProvider;

    /** @var MockObject&LoggerInterface */
    private LoggerInterface $logger;

    private WarmupRatesMessageHandler $handler;

    protected function setUp(): void
    {
        $this->ratesProvider = $this->createMock(RatesProviderInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new WarmupRatesMessageHandler(
            $this->ratesProvider,
            $this->logger
        );
    }

    /**
     * @throws Exception
     */
    public function testHandlerWarmsUpSnapshotViaRatesProvider(): void
    {
        $date = new DateTimeImmutable('2023-10-25');
        $message = new WarmupRatesMessage($date);

        $rates = new CbrRatesDto($date, [
            new CbrRateDto('USD', 1, 75.0, 75.0),
        ]);

        $this->ratesProvider->expects($this->once())
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
    public function testHandlerUsesDateFromMessage(): void
    {
        $date = new DateTimeImmutable('2024-03-15');
        $message = new WarmupRatesMessage($date);

        $this->ratesProvider->expects($this->once())
            ->method('getDailyByDate')
            ->with($this->equalTo($date))
            ->willReturn(null);

        ($this->handler)($message);
    }

    /**
     * @throws Exception
     */
    public function testHandlerLogsWarningWhenNoSnapshot(): void
    {
        $date = new DateTimeImmutable('2023-10-25');
        $message = new WarmupRatesMessage($date);

        $this->ratesProvider->expects($this->once())
            ->method('getDailyByDate')
            ->with($date)
            ->willReturn(null);

        $this->logger->expects($this->once())
            ->method('info');

        $this->logger->expects($this->once())
            ->method('warning');

        ($this->handler)($message);
    }

    public function testHandlerRethrowsExceptionForRetry(): void
    {
        $date = new DateTimeImmutable('2023-10-25');
        $message = new WarmupRatesMessage($date);

        $exception = new Exception('CBR fetch failed');

        $this->ratesProvider->expects($this->once())
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
