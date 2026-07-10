<?php

declare(strict_types=1);

namespace App\Domain\Currency\Exception;

/**
 * Represents an invalid exchange rate domain value.
 */
final class InvalidExchangeRateException extends \InvalidArgumentException
{
}
