<?php

namespace App\Messenger\Message;

/**
 * Сообщение для асинхронного прогрева snapshot'а курсов валют на конкретную дату.
 * Отправляется командой WarmupRatesCommand и обрабатывается WarmupRatesMessageHandler.
 */
final readonly class WarmupRatesMessage
{
    public function __construct(
        public \DateTimeImmutable $date,
    ) {
    }
}
