<?php

namespace App\Messenger\MessageHandler;

use App\Contract\CbrRatesSupplierInterface;
use App\Messenger\Message\CbrRatesCacheUpdateMessage;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Обработчик для асинхронного обновления кеша курсов валют
 * Выполняется в фоновом режиме через RabbitMQ worker
 */
#[AsMessageHandler]
readonly class CbrRatesCacheUpdateHandler
{
    public function __construct(
        private CbrRatesSupplierInterface $cbrRatesSupplier,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws Exception
     */
    public function __invoke(CbrRatesCacheUpdateMessage $message): void
    {
        $this->logger->info('Starting cache update for CBR rates', [
            'date' => $message->date->format('Y-m-d'),
            'handler' => __CLASS__,
        ]);

        try {
            $rates = $this->cbrRatesSupplier->getDailyByDate($message->date);

            if ($rates) {
                $this->logger->info('Successfully updated cache for CBR rates', [
                    'date' => $message->date->format('Y-m-d'),
                    'rates_count' => count($rates->rates),
                ]);
            } else {
                $this->logger->warning('No rates received for cache update', [
                    'date' => $message->date->format('Y-m-d'),
                ]);
            }
        } catch (Exception $exception) {
            $this->logger->error('Failed to update cache for CBR rates', [
                'date' => $message->date->format('Y-m-d'),
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception; // Re-throw для retry механизма
        }
    }
}
