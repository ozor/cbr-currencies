<?php

declare(strict_types=1);

namespace App\Exception;

use App\Exception\CbrRates\CbrRatesExceptionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ValidationException extends BadRequestHttpException implements CbrRatesExceptionInterface, ValidationExceptionInterface
{
    public function __construct(private readonly array $errors)
    {
        parent::__construct(message: 'Request validation error', code: 400);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
