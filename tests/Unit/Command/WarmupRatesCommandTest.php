<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\WarmupRatesCommand;
use App\Messenger\Message\WarmupRatesMessage;
use DG\BypassFinals;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class WarmupRatesCommandTest extends TestCase
{
    /** @var MockObject&MessageBusInterface */
    private MessageBusInterface $messageBus;

    private CommandTester $commandTester;
    private Envelope $envelope;

    protected function setUp(): void
    {
        BypassFinals::enable();

        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->envelope   = new Envelope(new \stdClass());

        $command             = new WarmupRatesCommand($this->messageBus);
        $this->commandTester = new CommandTester($command);
    }

    public function testExecuteDispatchesWarmupMessagesForPeriod(): void
    {
        $this->messageBus->expects($this->atLeast(1))
            ->method('dispatch')
            ->with($this->isInstanceOf(WarmupRatesMessage::class))
            ->willReturn($this->envelope);

        $this->commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('отправлен', $output);
    }

    public function testExecuteWithCustomDateAndDays(): void
    {
        $this->messageBus->expects($this->atLeast(1))
            ->method('dispatch')
            ->with($this->isInstanceOf(WarmupRatesMessage::class))
            ->willReturn($this->envelope);

        $this->commandTester->execute([
            'start-date' => '2024-01-10',
            '--days'     => '5',
        ]);

        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('2024-01-10', $output);
    }

    public function testExecuteSkipsWeekends(): void
    {
        // 2024-01-14 — воскресенье, период 7 дней содержит оба выходных
        $this->messageBus->expects($this->atLeast(1))
            ->method('dispatch')
            ->willReturn($this->envelope);

        $this->commandTester->execute([
            'start-date' => '2024-01-14',
            '--days'     => '7',
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Пропуск', $output);
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testExecuteDispatchesCorrectNumberOfWorkdayMessages(): void
    {
        // Период 2024-01-08 (пн) … 2024-01-14 (вс): 7 дней, из них 5 рабочих
        $dispatchCount = 0;
        $this->messageBus
            ->method('dispatch')
            ->willReturnCallback(function () use (&$dispatchCount) {
                $dispatchCount++;

                return $this->envelope;
            });

        $this->commandTester->execute([
            'start-date' => '2024-01-14',
            '--days'     => '7',
        ]);

        $this->assertEquals(5, $dispatchCount);
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testExecuteReturnsSuccessStatus(): void
    {
        $this->messageBus->method('dispatch')->willReturn($this->envelope);

        $this->commandTester->execute([
            'start-date' => '2024-06-30',
            '--days'     => '1',
        ]);

        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());
    }

    public function testExecuteFailsWithInvalidDate(): void
    {
        $this->commandTester->execute([
            'start-date' => 'invalid-date',
        ]);

        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Неверный формат даты', $output);
    }
}
