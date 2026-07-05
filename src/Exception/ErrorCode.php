<?php

declare(strict_types=1);

namespace App\Exception;

enum ErrorCode: string
{
    case VALIDATION_ERROR = 'validation_error';
    case RATE_NOT_FOUND = 'rate_not_found';
    case UPSTREAM_UNAVAILABLE = 'upstream_unavailable';
    case PARSE_ERROR = 'parse_error';
    case INTERNAL_ERROR = 'internal_error';
}
