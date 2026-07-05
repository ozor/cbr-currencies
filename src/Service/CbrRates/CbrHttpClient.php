<?php

namespace App\Service\CbrRates;

use App\Config\CbrRates;
use App\Exception\CbrRates\UpstreamUnavailableException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CbrHttpClient
{
    private const string URL_DAILY = '/scripts/XML_daily.asp';

    public function __construct(
        private readonly HttpClientInterface $cbrRatesClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getDailyXmlByDate(\DateTimeImmutable $date): string
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
            // Catching all possible exceptions from the HTTP client and rethrowing as UpstreamUnavailableException
        } catch (\Throwable $exception) {
            $this->logger->error($exception->getMessage(), [
                'exception' => $exception,
                'method' => __METHOD__,
                'date' => $date->format(CbrRates::RATE_CBR_DATE_FORMAT),
            ]);

            throw new UpstreamUnavailableException('CBR upstream unavailable.', 0, $exception);
        }
    }
}
