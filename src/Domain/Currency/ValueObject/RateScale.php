<?php

declare(strict_types=1);

namespace App\Domain\Currency\ValueObject;

/**
 * Defines precision rules for exchange rate calculations and API formatting.
 */
final class RateScale
{
    public const INTERNAL = 8;
    public const DISPLAY = 4;

    /**
     * Prevents instantiation because this class only contains calculation policy constants.
     */
    private function __construct()
    {
    }
}
