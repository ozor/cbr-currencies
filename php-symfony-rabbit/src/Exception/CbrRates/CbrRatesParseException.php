<?php

declare(strict_types=1);

namespace App\Exception\CbrRates;

use RuntimeException;
use Throwable;

class CbrRatesParseException extends RuntimeException implements CbrRatesExceptionInterface
{
    public function __construct(string $message = 'Failed to parse CBR rates XML.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
