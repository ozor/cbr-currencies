<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RateNotFoundException extends NotFoundHttpException implements CbrRatesExceptionInterface
{
    public function __construct()
    {
        parent::__construct('Rate not found.');
    }
}