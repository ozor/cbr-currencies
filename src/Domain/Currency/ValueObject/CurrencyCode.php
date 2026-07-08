<?php

declare(strict_types=1);

namespace App\Domain\Currency\ValueObject;

/**
 * Represents a normalized three-letter ISO-style currency code.
 */
final readonly class CurrencyCode
{
    private const CODE_PATTERN = '/^[A-Z]{3}$/';

    private string $value;

    /**
     * Creates a currency code from a normalized uppercase value.
     */
    private function __construct(string $value)
    {
        if (!preg_match(self::CODE_PATTERN, $value)) {
            throw new \InvalidArgumentException(sprintf('Invalid currency code "%s".', $value));
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self(strtoupper(trim($value)));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
