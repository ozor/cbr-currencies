<?php

namespace App\Exception;

use App\Exception\CbrRates\CbrRatesExceptionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class RequestValidationException extends BadRequestHttpException implements CbrRatesExceptionInterface, ValidationExceptionInterface
{
    /** @param array<string, string> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(message: 'Request validation error', code: 400);
    }

    /** @return array<string, string> */
    public function getErrors(): array
    {
        return $this->errors;
    }

}
