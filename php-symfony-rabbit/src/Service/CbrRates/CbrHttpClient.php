<?php

namespace App\Service\CbrRates;

use App\Config\CbrRates;
use App\Exception\CbrRates\CbrProviderException;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class CbrHttpClient
{
    private const string URL_DAILY = '/scripts/XML_daily.asp';

    public function __construct(
        private readonly HttpClientInterface $cbrRatesClient,
        private readonly LoggerInterface     $logger,
    ) {
    }

    public function getDailyXmlByDate(DateTimeImmutable $date): string
    {
        try {
            return $this->cbrRatesClient->request(
                'GET',
                self::URL_DAILY,
                [
                    'query' => [
                        'date_req' => $date->format(CbrRates::RATE_CBR_DATE_FORMAT),
                    ],
                ]
            )->getContent();
        // TODO: Catching all possible exceptions from the HTTP client and rethrowing as a CbrProviderException
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'exception' => $exception,
                'method' => __METHOD__,
                'date' => $date->format(CbrRates::RATE_CBR_DATE_FORMAT),
            ]);

            throw new CbrProviderException('CBR upstream unavailable.', 0, $exception);
        }
    }
}
