<?php

namespace App\Messenger\MessageHandler;

use App\Contract\RatesProviderInterface;
use App\Messenger\Message\WarmupRatesMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Обработчик warmup use case: прогревает дневной snapshot курсов через RatesProviderInterface.
 * За RatesProviderInterface стоит CachedRatesProvider, поэтому warmup прогревает
 * тот же cache layer, из которого читает sync API.
 */
#[AsMessageHandler]
readonly class WarmupRatesMessageHandler
{
    public function __construct(
        private RatesProviderInterface $ratesProvider,
        #[Autowire(service: 'monolog.logger.messenger')]
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(WarmupRatesMessage $message): void
    {
        $this->logger->info('Starting rates warmup for date', [
            'date' => $message->date->format('Y-m-d'),
            'handler' => __CLASS__,
        ]);

        try {
            $rates = $this->ratesProvider->getDailyByDate($message->date);

            if ($rates) {
                $this->logger->info('Successfully warmed up rates snapshot', [
                    'date' => $message->date->format('Y-m-d'),
                    'rates_count' => count($rates->rates),
                ]);
            } else {
                $this->logger->warning('No rates snapshot available for date', [
                    'date' => $message->date->format('Y-m-d'),
                ]);
            }
        } catch (\Exception $exception) {
            $this->logger->error('Failed to warm up rates snapshot', [
                'date' => $message->date->format('Y-m-d'),
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception; // Re-throw для retry механизма Messenger
        }
    }
}
