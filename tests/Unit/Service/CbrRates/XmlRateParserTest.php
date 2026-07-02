<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\CbrRates;

use App\Exception\CbrRates\ParseRatesException;
use App\Service\CbrRates\XmlRateParser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Unit test: covers only the exception-wrapping responsibility of XmlRateParser.
 * Full parsing pipeline is covered by XmlRateParserIntegrationTest.
 */
class XmlRateParserTest extends TestCase
{
    private XmlRateParser $parser;

    /** @var MockObject&SerializerInterface */
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->parser     = new XmlRateParser($this->serializer);
    }

    public function testParseWrapsSerializerExceptionInParseRatesException(): void
    {
        $original = new NotNormalizableValueException('parse failure');

        $this->serializer
            ->method('deserialize')
            ->willThrowException($original);

        try {
            $this->parser->parse('<invalid/>');
            $this->fail('ParseRatesException expected');
        } catch (ParseRatesException $e) {
            $this->assertSame($original, $e->getPrevious());
        }
    }
}
