<?php

namespace App\Exception\CbrRates;

use RuntimeException;
use Throwable;

class CbrProviderException extends RuntimeException implements CbrRatesExceptionInterface
{
    public function __construct(string $message = 'CBR upstream unavailable.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
