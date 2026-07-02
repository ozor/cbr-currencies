<?php

namespace App\Config;

final readonly class CbrRates
{
    public const RATE_REQUEST_DATE_FORMAT = 'Y-m-d';
    public const RATE_CBR_DATE_FORMAT = 'd/m/Y';
    public const BASE_CURRENCY_CODE_DEFAULT = 'RUR';
    public const CURRENCY_CODE_LENGTH = 3;
    public const CURRENCY_VALUE_PRECISION = 4;
}