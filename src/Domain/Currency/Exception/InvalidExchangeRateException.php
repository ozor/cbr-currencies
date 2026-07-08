<?php

declare(strict_types=1);

namespace App\Domain\Currency\Exception;

use InvalidArgumentException;

/**
 * Represents an invalid exchange rate domain value.
 */
final class InvalidExchangeRateException extends InvalidArgumentException
{
}
