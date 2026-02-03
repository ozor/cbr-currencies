<?php

namespace App\Tests\Command;

use App\Command\CbrWarmupCacheCommand;
use App\Messenger\Message\CbrRatesCacheUpdateMessage;
use DG\BypassFinals;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class CbrWarmupCacheCommandTest extends TestCase
{
    /** @var MockObject&MessageBusInterface */
    private MessageBusInterface $messageBus;

    private CommandTester $commandTester;
    private Envelope $envelope;

    protected function setUp(): void
    {
        BypassFinals::enable();

        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->envelope = $this->createStub(Envelope::class);

        $command = new CbrWarmupCacheCommand($this->messageBus);
        $this->commandTester = new CommandTester($command);
    }

    public function testExecuteWithDefaultParameters(): void
    {
        $this->messageBus->expects($this->atLeast(1))
            ->method('dispatch')
            ->with($this->isInstanceOf(CbrRatesCacheUpdateMessage::class))
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
            ->with($this->isInstanceOf(CbrRatesCacheUpdateMessage::class))
            ->willReturn($this->envelope);

        $this->commandTester->execute([
            'start-date' => '2024-01-10',
            '--days' => '5',
        ]);

        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('2024-01-10', $output);
    }

    public function testExecuteSkipsWeekends(): void
    {
        $this->messageBus->expects($this->atLeast(1))
            ->method('dispatch')
            ->willReturn($this->envelope);

        $this->commandTester->execute([
            'start-date' => '2024-01-14',
            '--days' => '7',
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Пропуск', $output);
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
