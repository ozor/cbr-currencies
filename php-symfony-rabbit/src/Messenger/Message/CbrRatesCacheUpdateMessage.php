<?php

namespace App\Messenger\Message;

use DateTimeImmutable;

/**
 * Сообщение для асинхронного обновления кеша курсов валют
 * Используется для фонового обновления данных без блокировки API запросов
 */
final readonly class CbrRatesCacheUpdateMessage
{
    public function __construct(
        public DateTimeImmutable $date,
    ) {
    }
}
