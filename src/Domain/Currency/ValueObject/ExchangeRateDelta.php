<?php

declare(strict_types=1);

namespace App\Domain\Currency\ValueObject;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Represents a difference between two exchange rate values.
 *
 * Unlike an exchange rate itself, a delta may be positive, zero, or negative.
 */
final readonly class ExchangeRateDelta
{
    private BigDecimal $value;

    /**
     * Creates an exchange rate delta from a decimal value.
     */
    public function __construct(BigDecimal $value)
    {
        $this->value = $value;
    }

    /**
     * Creates an exchange rate delta from a decimal string.
     */
    public static function fromString(string $value): self
    {
        $normalized = trim($value);

        if (!preg_match('/^-?\d+(\.\d+)?$/', $normalized)) {
            throw new \InvalidArgumentException(sprintf('Invalid exchange rate delta "%s".', $value));
        }

        return new self(BigDecimal::of($normalized));
    }

    /**
     * Returns this delta rounded to the public display scale.
     */
    public function toDisplayString(): string
    {
        return (string) $this->value->toScale(
            RateScale::DISPLAY,
            RoundingMode::HALF_UP,
        );
    }

    /**
     * Returns this delta rounded to the internal calculation scale.
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

    public function isNegative(): bool
    {
        return $this->value->isLessThan(BigDecimal::zero());
    }

    public function isZero(): bool
    {
        return $this->value->isEqualTo(BigDecimal::zero());
    }

    public function isPositive(): bool
    {
        return $this->value->isGreaterThan(BigDecimal::zero());
    }

    public function __toString(): string
    {
        return $this->toDisplayString();
    }
}
