<?php

namespace App\Messenger\MessageHandler;

use App\Exception\CbrRates\CbrRateNotFoundException;
use App\Messenger\Message\CbrRatesRequestMessage;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
readonly class CbrRequestHandler
{
    public function __construct(
        private HttpClientInterface $cbrRatesClient,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CbrRatesRequestMessage $message): string
    {
        try {
            // Set random delay for avoid spam ban from CBR
            usleep(random_int(100000, 500000));

            return $this->cbrRatesClient->request(
                $message->method,
                $message->url,
                [
                    'query' => $message->query,
                ]
            )->getContent();
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage(), [
                'exception' => $exception,
                'method' => __METHOD__,
                'message' => $message,
            ]);
            throw new CbrRateNotFoundException();
        }
    }
}
