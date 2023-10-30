<?php

namespace App\Tests\Service\CbrRates;

use App\Dto\CbrRates\CbrRateDto;
use App\Dto\CbrRates\CbrRatesDto;
use App\Messenger\Message\CbrRatesRequestMessage;
use App\Service\CbrRates\CbrRatesSupplier;
use DateTimeImmutable;
use DG\BypassFinals;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;

class CbrRatesSupplierTest extends TestCase
{
    private DateTimeImmutable $date;

    /** @var MockObject&SerializerInterface */
    private SerializerInterface $serializer;

    /** @var MockObject&Envelope */
    private Envelope $envelope;

    /** @var MockObject&HandledStamp */
    private HandledStamp $handledStamp;

    /** @var MockObject&MessageBusInterface */
    private MessageBusInterface $messageBus;

    private CbrRatesDto $cbrRatesDto;
    private CbrRatesDto $cbrRatesDtoFinal;

    public function setUp(): void
    {
        $this->date = new DateTimeImmutable('2023-10-25');

        $usd = new CbrRateDto('USD', 1, 73.1234, 73.1234);
        $rur = new CbrRateDto('RUR', 1, 1.0, 1.0);
        $this->cbrRatesDto = new CbrRatesDto($this->date, [$usd]);
        $this->cbrRatesDtoFinal = new CbrRatesDto($this->date, [$usd, $rur]);

        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        BypassFinals::enable();
        $this->envelope = $this->createStub(Envelope::class);
        $this->handledStamp = $this->createStub(HandledStamp::class);
    }

    public function testGetDailyByDate()
    {
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CbrRatesRequestMessage::class))
            ->willReturn($this->envelope);

        $this->envelope
            ->expects($this->once())
            ->method('last')
            ->willReturn($this->handledStamp);

        $this->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->willReturn($this->cbrRatesDto);

        $service = new CbrRatesSupplier($this->serializer, $this->messageBus);

        $result = $service->getDailyByDate($this->date);

        $this->assertEquals($this->cbrRatesDtoFinal, $result);
    }
}
