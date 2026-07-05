<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\CbrRates;

use App\Dto\CbrRates\CbrRateDto;
use App\Dto\CbrRates\CbrRatesDto;
use App\Service\CbrRates\CbrHttpClient;
use App\Service\CbrRates\CbrRatesSupplier;
use App\Service\CbrRates\XmlRateParser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CbrRatesSupplierTest extends TestCase
{
    private \DateTimeImmutable $date;

    /** @var MockObject&CbrHttpClient */
    private CbrHttpClient $cbrHttpClient;

    /** @var MockObject&XmlRateParser */
    private XmlRateParser $xmlRateParser;

    private CbrRatesDto $parsedDto;
    private CbrRatesDto $expectedFinalDto;

    public function setUp(): void
    {
        $this->date = new \DateTimeImmutable('2023-10-25');

        $usd = new CbrRateDto('USD', 1, 73.1234, 73.1234);
        $rur = new CbrRateDto('RUR', 1, 1.0, 1.0);
        $this->parsedDto = new CbrRatesDto($this->date, [$usd]);
        $this->expectedFinalDto = new CbrRatesDto($this->date, [$usd, $rur]);

        $this->cbrHttpClient = $this->createMock(CbrHttpClient::class);
        $this->xmlRateParser = $this->createMock(XmlRateParser::class);
    }

    public function testGetDailyByDate(): void
    {
        $xmlContent = '<ValCurs></ValCurs>';

        $this->cbrHttpClient
            ->expects($this->once())
            ->method('getDailyXmlByDate')
            ->with($this->date)
            ->willReturn($xmlContent);

        $this->xmlRateParser
            ->expects($this->once())
            ->method('parse')
            ->with($xmlContent)
            ->willReturn($this->parsedDto);

        $supplier = new CbrRatesSupplier($this->cbrHttpClient, $this->xmlRateParser);

        $result = $supplier->getDailyByDate($this->date);

        $this->assertEquals($this->expectedFinalDto, $result);
    }

    public function testSupplierAddsBaseCurrency(): void
    {
        $this->cbrHttpClient->method('getDailyXmlByDate')->willReturn('<ValCurs></ValCurs>');
        $this->xmlRateParser->method('parse')->willReturn($this->parsedDto);

        $supplier = new CbrRatesSupplier($this->cbrHttpClient, $this->xmlRateParser);
        $result = $supplier->getDailyByDate($this->date);
        $this->assertInstanceOf(CbrRatesDto::class, $result);

        $codes = array_map(fn (CbrRateDto $r) => $r->code, $result->rates);
        $this->assertContains('RUR', $codes);
    }
}
