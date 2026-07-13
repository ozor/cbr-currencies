<?php

declare(strict_types=1);

namespace App\Service\CbrRates;

use App\Config\CbrRates;
use App\Domain\Currency\Model\ExchangeRate;
use App\Domain\Currency\ValueObject\CurrencyCode;
use App\Domain\Currency\ValueObject\ExchangeRateValue;
use App\Dto\CbrRates\CbrRateDto;

final readonly class CbrRateNormalizer
{
    /**
     * Converts a raw CBR rate DTO into a normalized exchange rate value.
     */
    public function normalizeValue(CbrRateDto $rate): ExchangeRateValue
    {
        return ExchangeRateValue::fromString($rate->vunitRate);
    }

    /**
     * Converts a raw CBR currency code into a domain currency code.
     */
    public function normalizeCode(CbrRateDto $rate): CurrencyCode
    {
        return CurrencyCode::fromString($rate->code);
    }

    /**
     * Converts a raw CBR rate into a normalized exchange rate.
     */
    public function normalize(CbrRateDto $rate): ExchangeRate
    {
        return new ExchangeRate(
            currencyCode: CurrencyCode::fromString($rate->code),
            baseCurrencyCode: CurrencyCode::fromString(CbrRates::BASE_CURRENCY_CODE_DEFAULT),
            valuePerUnit: ExchangeRateValue::fromString($rate->vunitRate),
        );
    }
}
