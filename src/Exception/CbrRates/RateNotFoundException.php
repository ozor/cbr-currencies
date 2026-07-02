<?php

declare(strict_types=1);

namespace App\Exception\CbrRates;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RateNotFoundException extends NotFoundHttpException implements CbrRatesExceptionInterface
{
    public function __construct(string $message = 'Rate not found.')
    {
        parent::__construct($message);
    }
}
