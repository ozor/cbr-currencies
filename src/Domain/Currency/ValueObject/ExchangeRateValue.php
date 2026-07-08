<?php

declare(strict_types=1);

namespace App\Domain\Currency\ValueObject;

use App\Domain\Currency\Exception\InvalidExchangeRateException;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Represents a positive exchange rate value with arbitrary decimal precision.
 *
 * This value object is used for rates such as "1 USD = 92.1234 RUB".
 */
final readonly class ExchangeRateValue
{
    private BigDecimal $value;

    /**
     * Creates a positive exchange rate value.
     */
    private function __construct(BigDecimal $value)
    {
        if ($value->isLessThanOrEqualTo(BigDecimal::zero())) {
            throw new InvalidExchangeRateException('Exchange rate value must be positive.');
        }

        $this->value = $value;
    }

    /**
     * Creates an exchange rate value from a normalized decimal string.
     *
     * Accepted examples: "92", "92.1", "92.1234", "0.6123".
     * Rejected examples: "0", "-1", "92,1234", "abc".
     */
    public static function fromString(string $value): self
    {
        $normalized = trim($value);

        if (!preg_match('/^\d+(\.\d+)?$/', $normalized)) {
            throw new InvalidExchangeRateException(sprintf('Invalid exchange rate value "%s".', $value));
        }

        return new self(BigDecimal::of($normalized));
    }

    public static function fromBigDecimal(BigDecimal $value): self
    {
        return new self($value);
    }

    /**
     * Divides this exchange rate by another exchange rate.
     *
     * This is used to calculate cross-rates, for example, USD/RUB divided by EUR/RUB.
     */
    public function divideBy(self $other): self
    {
        return new self(
            $this->value->dividedBy(
                $other->value,
                RateScale::INTERNAL,
                RoundingMode::HALF_UP,
            ),
        );
    }

    /**
     * Calculates the difference between this exchange rate and another exchange rate.
     */
    public function minus(self $other): ExchangeRateDelta
    {
        return new ExchangeRateDelta(
            $this->value->minus($other->value),
        );
    }

    /**
     * Returns this exchange rate rounded to the public display scale.
     */
    public function toDisplayString(): string
    {
        return (string) $this->value->toScale(
            RateScale::DISPLAY,
            RoundingMode::HALF_UP,
        );
    }

    /**
     * Returns this exchange rate rounded to the internal calculation scale.
     */
    public function toInternalString(): string
    {
        return (string) $this->value->toScale(
            RateScale::INTERNAL,
            RoundingMode::HALF_UP,
        );
    }

    public function raw(): BigDecimal
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value->isEqualTo($other->value);
    }

    public function __toString(): string
    {
        return $this->toDisplayString();
    }
}
