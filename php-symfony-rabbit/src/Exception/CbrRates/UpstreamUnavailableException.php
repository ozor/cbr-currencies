<?php

declare(strict_types=1);

namespace App\Exception\CbrRates;

use RuntimeException;
use Throwable;

class UpstreamUnavailableException extends RuntimeException implements CbrRatesExceptionInterface
{
    public function __construct(string $message = 'CBR upstream unavailable.', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
