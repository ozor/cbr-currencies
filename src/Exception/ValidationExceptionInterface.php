<?php

namespace App\Exception;

interface ValidationExceptionInterface
{
    /** @return array<string, string> */
    public function getErrors(): array;
}
