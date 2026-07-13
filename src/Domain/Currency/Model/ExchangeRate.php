<?php

declare(strict_types=1);

namespace App\Domain\Currency\Model;

use App\Domain\Currency\ValueObject\CurrencyCode;
use App\Domain\Currency\ValueObject\ExchangeRateValue;

/**
 * Represents a normalized exchange rate against a base currency.
 */
final readonly class ExchangeRate
{
    /**
     * Creates a normalized exchange rate.
     */
    public function __construct(
        private CurrencyCode $currencyCode,
        private CurrencyCode $baseCurrencyCode,
        private ExchangeRateValue $valuePerUnit,
    ) {
    }

    /**
     * Returns the quoted currency code.
     */
    public function currencyCode(): CurrencyCode
    {
        return $this->currencyCode;
    }

    /**
     * Returns the base currency code.
     */
    public function baseCurrencyCode(): CurrencyCode
    {
        return $this->baseCurrencyCode;
    }

    /**
     * Returns the normalized value of one currency unit in base currency.
     */
    public function valuePerUnit(): ExchangeRateValue
    {
        return $this->valuePerUnit;
    }
}
