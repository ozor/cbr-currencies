<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\CbrRates;

use App\Dto\CbrRates\CbrRatesDto;
use App\Service\CbrRates\CbrRatesDenormalizer;
use PHPUnit\Framework\TestCase;

class CbrRatesDenormalizerTest extends TestCase
{
    public function testDenormalizesCbrDecimalValuesAsStrings(): void
    {
        $data = [
            '@Date' => '10.01.2024',
            'Valute' => [
                [
                    'CharCode' => 'USD',
                    'Nominal' => '1',
                    'Value' => '92,1234',
                    'VunitRate' => '92,1234',
                ],
                [
                    'CharCode' => 'JPY',
                    'Nominal' => '100',
                    'Value' => '61,2300',
                    'VunitRate' => '0,6123',
                ],
            ],
        ];

        $denormalizer = new CbrRatesDenormalizer();

        /** @var CbrRatesDto $dto */
        $dto = $denormalizer->denormalize($data, CbrRatesDto::class);

        $rates = $dto->rates;

        self::assertSame('USD', $rates[0]->code);
        self::assertSame(1, $rates[0]->nominal);
        self::assertSame('92.1234', $rates[0]->value);
        self::assertSame('92.1234', $rates[0]->vunitRate);

        self::assertSame('JPY', $rates[1]->code);
        self::assertSame(100, $rates[1]->nominal);
        self::assertSame('61.2300', $rates[1]->value);
        self::assertSame('0.6123', $rates[1]->vunitRate);
    }
}
