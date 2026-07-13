<?php

namespace App\Tests\Unit\Service\CbrRates;

use App\Dto\CbrRates\CbrRateDto;
use App\Service\CbrRates\CbrRateNormalizer;
use PHPUnit\Framework\TestCase;

class CbrRateNormalizerTest extends TestCase
{
    public function testNormalizesCurrencyCode(): void
    {
        $normalizer = new CbrRateNormalizer();

        $rate = new CbrRateDto(
            code: 'usd',
            nominal: 1,
            value: '92.1234',
            vunitRate: '92.1234',
        );

        $exchangeRate = $normalizer->normalize($rate);

        self::assertSame('USD', $exchangeRate->currencyCode()->value());
    }

    public function testNormalizesValuePerUnitFromVunitRate(): void
    {
        $normalizer = new CbrRateNormalizer();

        $rate = new CbrRateDto(
            code: 'JPY',
            nominal: 100,
            value: '61.2300',
            vunitRate: '0.6123',
        );

        $exchangeRate = $normalizer->normalize($rate);

        self::assertSame('0.6123', $exchangeRate->valuePerUnit()->toDisplayString());
    }

    public function testDoesNotUseRawValueWhenNominalIsNotOne(): void
    {
        $normalizer = new CbrRateNormalizer();

        $rate = new CbrRateDto(
            code: 'JPY',
            nominal: 100,
            value: '61.2300',
            vunitRate: '0.6123',
        );

        $exchangeRate = $normalizer->normalize($rate);

        self::assertNotSame('61.2300', $exchangeRate->valuePerUnit()->toDisplayString());
        self::assertSame('0.6123', $exchangeRate->valuePerUnit()->toDisplayString());
    }

    public function testThrowsWhenCurrencyCodeIsInvalid(): void
    {
        $normalizer = new CbrRateNormalizer();

        $rate = new CbrRateDto(
            code: 'USDX',
            nominal: 1,
            value: '92.1234',
            vunitRate: '92.1234',
        );

        $this->expectException(\InvalidArgumentException::class);

        $normalizer->normalize($rate);
    }
}
