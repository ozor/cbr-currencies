<?php

namespace App\Exception\CbrRates;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PreviousTradingDayNotFoundException extends NotFoundHttpException implements CbrRatesExceptionInterface
{
    public function __construct()
    {
        parent::__construct('Previous trading day not found within limit.');
    }
}
