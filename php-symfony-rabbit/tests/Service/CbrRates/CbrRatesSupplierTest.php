<?php

namespace App\Tests\Service\CbrRates;

use App\Dto\CbrRateDto;
use App\Dto\CbrRatesDto;
use App\Messenger\Message\CbrRatesRequestMessage;
use App\Service\CbrRates\CbrRatesSupplier;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;

class CbrRatesSupplierTest extends TestCase
{
    private DateTimeImmutable $date;

    private string $cacheKey;

    /** @var MockObject&SerializerInterface */
    private SerializerInterface $serializer;

    /** @var MockObject&CacheInterface */
    private CacheInterface $cache;

    /** @var MockObject&MessageBusInterface */
    private MessageBusInterface $messageBus;

    private CbrRatesDto $cbrRatesDto;

    public function setUp(): void
    {
        $this->date = new DateTimeImmutable('2023-10-25');

        $this->cacheKey = sprintf('CbrRatesDaily.%s', $this->date->format('Y-m-d'));

        $this->cbrRatesDto = new CbrRatesDto(
            $this->date,
            [
                new CbrRateDto('USD', 1, 73.1234, 73.1234),
            ]
        );

        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
    }

    public function testGetDailyByDateReturnsCachedValue()
    {
        $this->cache->expects($this->once())
            ->method('get')
            ->with($this->cacheKey)
            ->willReturn($this->cbrRatesDto);

        $service = new CbrRatesSupplier($this->serializer, $this->cache, $this->messageBus);

        $result = $service->getDailyByDate($this->date);

        $this->assertEquals($this->cbrRatesDto, $result);
    }

    public function testGetDailyByDateFetchesDataIfNotCached()
    {
        $this->cache->expects($this->once())
            ->method('get')
            ->with($this->cacheKey)
            ->willReturn(null);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CbrRatesRequestMessage::class))
            ->willReturn(new Envelope($this->cbrRatesDto));

        $this->cache->expects($this->once())
            ->method('set')
            ->with($this->cacheKey, $this->cbrRatesDto);

        $service = new CbrRatesSupplier($this->serializer, $this->cache, $this->messageBus);

        $result = $service->getDailyByDate($this->date);

        $this->assertEquals($this->cbrRatesDto, $result);
    }
}
