<?php

namespace App\Messenger\MessageHandler;

use App\Messenger\Message\CbrRatesRequestMessage;
use Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
readonly class CbrRequestHandler
{
    public function __construct(
        private HttpClientInterface $cbrRatesClient,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws Exception
     */
    public function __invoke(CbrRatesRequestMessage $message): string
    {
        // Set random delay for avoid spam ban from CBR
        usleep(random_int(100000, 500000));

        return $this->cbrRatesClient->request(
            $message->method,
            $message->url,
            [
                'query' => $message->query,
            ]
        )->getContent();
    }
}
