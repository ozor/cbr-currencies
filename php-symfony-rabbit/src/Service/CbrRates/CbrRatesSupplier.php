<?php

namespace App\Service\CbrRates;

use App\Config\CbrRates;
use App\Contract\CbrRatesSupplierInterface;
use App\Dto\CbrRatesDto;
use App\Messenger\Message\CbrRatesRequestMessage;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Serializer\SerializerInterface;

class CbrRatesSupplier implements CbrRatesSupplierInterface
{
    private const URL_DAILY = '/scripts/XML_daily.asp';
    private const FORMAT_XML = 'xml';

    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function getDailyByDate(DateTimeImmutable $date): ?CbrRatesDto
    {
        $cbrRatesRequestMessage = new CbrRatesRequestMessage(
            method: Request::METHOD_GET,
            url: self::URL_DAILY,
            query: [
                'date_req' => $date->format(CbrRates::RATE_DATE_FORMAT),
            ],
        );

        return $this->getRates($cbrRatesRequestMessage);
    }

    private function getRates(CbrRatesRequestMessage $message): ?CbrRatesDto
    {
        $envelop = $this->messageBus->dispatch($message);

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
