<?php

namespace App\Exception;

interface ValidationExceptionInterface
{
    public function getErrors(): array;
}