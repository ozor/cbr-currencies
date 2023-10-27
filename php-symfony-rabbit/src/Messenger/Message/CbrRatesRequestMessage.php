<?php

namespace App\Messenger\Message;

final readonly class CbrRatesRequestMessage
{
    public function __construct(
        public string $method,
        public string $url,
        public array $query,
    ) {
    }
}
