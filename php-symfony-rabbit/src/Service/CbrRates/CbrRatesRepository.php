<?php

namespace App\Service\CbrRates;

use App\Dto\CbrRateDto;
use App\Dto\CbrRatesDto;
use App\Config\CbrRates;
use DateTimeImmutable;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CbrRatesRepository
{
    private const URL_DAILY = '/scripts/XML_daily.asp';
    private const FORMAT_XML = 'xml';

    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly HttpClientInterface $cbrRatesClient,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function findByDateAndCode(DateTimeImmutable $date, string $code): CbrRateDto
    {
        $rates = array_filter(
            $this->getRates($date)?->rates ?? [],
            fn(CbrRateDto $rate) => $rate->code === $code
        );
        if (empty($rates)) {
            throw new \RuntimeException('Rates not found');
        }
        return array_shift($rates);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getRates(DateTimeImmutable $date): CbrRatesDto
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