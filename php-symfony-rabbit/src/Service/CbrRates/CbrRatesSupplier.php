<?php

namespace App\Service\CbrRates;

use App\Config\CbrRates;
use App\Dto\CbrRatesDto;
use App\Messenger\Message\CbrRatesRequestMessage;
use DateTimeImmutable;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;

class CbrRatesSupplier
{
    private const URL_DAILY = '/scripts/XML_daily.asp';
    private const FORMAT_XML = 'xml';

    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly CacheInterface $cache,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getDailyByDate(DateTimeImmutable $date): ?CbrRatesDto
    {
        return $this->cache->get(
            sprintf('CbrRatesDaily.%s', $date->format('Y-m-d')),
            function () use ($date): ?CbrRatesDto {
                return $this->getRates($date);
            }
        );
    }

    private function getRates(DateTimeImmutable $date): ?CbrRatesDto
    {
        $envelop = $this->messageBus->dispatch(
            new CbrRatesRequestMessage(
                method: Request::METHOD_GET,
                url: self::URL_DAILY,
                query: [
                    'date_req' => $date->format(CbrRates::RATE_DATE_FORMAT),
                ],
            )
        );

        $handledStamp = $envelop->last(HandledStamp::class);
        if ($handledStamp instanceof HandledStamp) {
            return $this->serializer->deserialize(
                $handledStamp->getResult(),
                CbrRatesDto::class,
                self::FORMAT_XML
            );
        }

        return null;
    }
}
