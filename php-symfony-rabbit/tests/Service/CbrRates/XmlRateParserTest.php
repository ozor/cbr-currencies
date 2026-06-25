<?php

declare(strict_types=1);

namespace App\Tests\Service\CbrRates;

use App\Dto\CbrRates\CbrRateDto;
use App\Dto\CbrRates\CbrRatesDto;
use App\Exception\CbrRates\CbrRatesParseException;
use App\Service\CbrRates\XmlRateParser;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\SerializerInterface;

class XmlRateParserTest extends TestCase
{
    private const string VALID_XML = <<<XML
        <ValCurs Date="25.10.2023" name="Foreign Currency Market">
            <Valute ID="R01235">
                <NumCode>840</NumCode>
                <CharCode>USD</CharCode>
                <Nominal>1</Nominal>
                <Name>US Dollar</Name>
                <Value>93,1234</Value>
                <VunitRate>93,1234</VunitRate>
            </Valute>
            <Valute ID="R01239">
                <NumCode>978</NumCode>
                <CharCode>EUR</CharCode>
                <Nominal>1</Nominal>
                <Name>Euro</Name>
                <Value>98,4567</Value>
                <VunitRate>98,4567</VunitRate>
            </Valute>
        </ValCurs>
        XML;

    private XmlRateParser $parser;

    /** @var MockObject&SerializerInterface */
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->parser     = new XmlRateParser($this->serializer);
    }

    public function testParseReturnsCbrRatesDto(): void
    {
        $expectedDto = new CbrRatesDto(
            new DateTimeImmutable('2023-10-25'),
            [
                new CbrRateDto('USD', 1, 93.1234, 93.1234),
            ]
        );

        $this->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with(self::VALID_XML, CbrRatesDto::class, 'xml')
            ->willReturn($expectedDto);

        $result = $this->parser->parse(self::VALID_XML);

        $this->assertSame($expectedDto, $result);
    }

    public function testParsePassesXmlStringToSerializer(): void
    {
        $xml         = '<ValCurs Date="01.01.2024"><Valute /></ValCurs>';
        $expectedDto = new CbrRatesDto(new DateTimeImmutable('2024-01-01'), []);

        $this->serializer
            ->expects($this->once())
            ->method('deserialize')
            ->with($xml, CbrRatesDto::class, 'xml')
            ->willReturn($expectedDto);

        $this->parser->parse($xml);
    }

    public function testParseThrowsCbrRatesParseExceptionOnSerializerFailure(): void
    {
        $this->expectException(CbrRatesParseException::class);

        $this->serializer
            ->method('deserialize')
            ->willThrowException(new NotNormalizableValueException('bad xml'));

        $this->parser->parse('<invalid/>');
    }

    public function testParseExceptionPreservesOriginalException(): void
    {
        $original = new NotNormalizableValueException('parse failure');

        $this->serializer
            ->method('deserialize')
            ->willThrowException($original);

        try {
            $this->parser->parse('<invalid/>');
            $this->fail('CbrRatesParseException expected');
        } catch (CbrRatesParseException $e) {
            $this->assertSame($original, $e->getPrevious());
        }
    }
}
