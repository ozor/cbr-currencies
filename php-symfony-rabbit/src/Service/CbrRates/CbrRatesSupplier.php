<?php

namespace App\Service\CbrRates;

use App\Config\CbrRates;
use App\Dto\CbrRatesDto;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CbrRatesSupplier
{
    private const URL_DAILY = '/scripts/XML_daily.asp';
    private const FORMAT_XML = 'xml';

    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly HttpClientInterface $cbrRatesClient,
        private readonly CacheInterface $cache,
    ) {
    }

    public function __invoke(DateTimeImmutable $date): CbrRatesDto
    {
        return $this->cache->get(
            sprintf('CbrRates.%s', $date->format('Y-m-d')),
            fn() => $this->serializer->deserialize(
                $this->getRatesXml($date),
                CbrRatesDto::class,
                self::FORMAT_XML
            )
        );
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    private function getRatesXml(DateTimeImmutable $date): string
    {
        return $this->cbrRatesClient->request(
            Request::METHOD_GET,
            self::URL_DAILY,
            [
                'query' => [
                    'date_req' => $date->format(CbrRates::RATE_DATE_FORMAT),
                ],
            ]
        )->getContent();
    }
}