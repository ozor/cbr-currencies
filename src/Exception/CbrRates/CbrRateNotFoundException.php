<?php

namespace App\Exception\CbrRates;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CbrRateNotFoundException extends NotFoundHttpException implements CbrRatesExceptionInterface
{
    public function __construct()
    {
        parent::__construct('Rate not found.');
    }
}