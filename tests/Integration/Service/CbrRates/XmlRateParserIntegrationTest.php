<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\CbrRates;

use App\Exception\CbrRates\ParseRatesException;
use App\Service\CbrRates\CbrRatesDenormalizer;
use App\Service\CbrRates\XmlRateParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * Integration test for XmlRateParser using a real Symfony Serializer + CbrRatesDenormalizer.
 * No mocks — exercises the full XML-to-DTO parsing pipeline.
 * Does NOT depend on HTTP client or broker.
 */
class XmlRateParserIntegrationTest extends TestCase
{
    private XmlRateParser $parser;

    protected function setUp(): void
    {
        $serializer = new Serializer(
            [new CbrRatesDenormalizer()],
            [new XmlEncoder()]
        );

        $this->parser = new XmlRateParser($serializer);
    }

    private function loadFixture(string $filename): string
    {
        $path = __DIR__.'/../../fixtures/'.$filename;
        $this->assertFileExists($path, "Fixture file not found: $path");

        return (string) file_get_contents($path);
    }

    // -------------------------------------------------------------------------
    // Happy path — correct date and currency list
    // -------------------------------------------------------------------------

    public function testParsesDateCorrectly(): void
    {
        $result = $this->parser->parse($this->loadFixture('cbr_rates_sample.xml'));

        $this->assertSame('2023-10-25', $result->tradingDate->format('Y-m-d'));
    }

    public function testParsesCurrencyList(): void
    {
        $result = $this->parser->parse($this->loadFixture('cbr_rates_sample.xml'));

        $codes = array_map(fn ($r) => $r->code, $result->rates);

        $this->assertContains('USD', $codes);
        $this->assertContains('EUR', $codes);
        $this->assertCount(2, $result->rates);
    }

    public function testParsesCommaSeparatedValuesAsDecimalStrings(): void
    {
        $result = $this->parser->parse($this->loadFixture('cbr_rates_sample.xml'));

        $usd = array_values(array_filter($result->rates, fn ($r) => 'USD' === $r->code))[0];
        $eur = array_values(array_filter($result->rates, fn ($r) => 'EUR' === $r->code))[0];

        $this->assertSame('93.1234', $usd->value);
        $this->assertSame('93.1234', $usd->vunitRate);
        $this->assertSame('98.4567', $eur->value);
    }

    public function testParsesNominalAsInteger(): void
    {
        $result = $this->parser->parse($this->loadFixture('cbr_rates_sample.xml'));

        foreach ($result->rates as $rate) {
            $this->assertIsInt($rate->nominal);
            $this->assertSame(1, $rate->nominal);
        }
    }

    // -------------------------------------------------------------------------
    // Error handling — invalid input throws ParseRatesException
    // -------------------------------------------------------------------------

    public function testThrowsParseRatesExceptionOnMalformedXml(): void
    {
        $this->expectException(ParseRatesException::class);

        $this->parser->parse('<<< this is not valid xml >>>');
    }

    public function testThrowsParseRatesExceptionOnEmptyString(): void
    {
        $this->expectException(ParseRatesException::class);

        $this->parser->parse('');
    }

    public function testParseRatesExceptionPreservesOriginalCause(): void
    {
        try {
            $this->parser->parse('<<< invalid >>>');
            $this->fail('ParseRatesException expected');
        } catch (ParseRatesException $e) {
            $this->assertNotNull($e->getPrevious(), 'ParseRatesException must chain the original cause');
        }
    }
}
