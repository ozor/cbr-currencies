<?php

namespace App\Tests\Service\CbrRates;

use App\Dto\CbrRates\CbrRateDto;
use App\Dto\CbrRates\CbrRatesDto;
use App\Service\CbrRates\CbrHttpClient;
use App\Service\CbrRates\CbrRatesSupplier;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

class CbrRatesSupplierTest extends TestCase
{
    private DateTimeImmutable $date;

    /** @var MockObject&SerializerInterface */
    private SerializerInterface $serializer;

    /** @var MockObject&CbrHttpClient */
    private CbrHttpClient $cbrHttpClient;

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
        $this->cbrHttpClient = $this->createMock(CbrHttpClient::class);
    }

    /**
     * @throws ExceptionInterface
     */
    public function testGetDailyByDate(): void
    {
        $xmlContent = '<ValCurs></ValCurs>';

        $this->cbrHttpClient
            ->expects($this->once())
            ->method('getDailyXmlByDate')
            ->with($this->date)
            ->willReturn($xmlContent);

        $this->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with($xmlContent, CbrRatesDto::class, 'xml')
            ->willReturn($this->cbrRatesDto);

        $service = new CbrRatesSupplier($this->serializer, $this->cbrHttpClient);

        $result = $service->getDailyByDate($this->date);

        $this->assertEquals($this->cbrRatesDtoFinal, $result);
    }
}
