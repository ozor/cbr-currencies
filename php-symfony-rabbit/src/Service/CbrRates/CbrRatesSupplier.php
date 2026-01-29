<?php

namespace App\Service\CbrRates;

use App\Config\CbrRates;
use App\Contract\CbrRatesSupplierInterface;
use App\Dto\CbrRates\CbrRateDto;
use App\Dto\CbrRates\CbrRatesDto;
use App\Messenger\Message\CbrRatesRequestMessage;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

class CbrRatesSupplier implements CbrRatesSupplierInterface
{
    private const string URL_DAILY = '/scripts/XML_daily.asp';
    private const string FORMAT_XML = 'xml';

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
                'date_req' => $date->format(CbrRates::RATE_CBR_DATE_FORMAT),
            ],
        );

        return $this->getRates($cbrRatesRequestMessage);
    }

    private function getRates(CbrRatesRequestMessage $message): ?CbrRatesDto
    {
        $envelop = $this->messageBus->dispatch($message);

        $handledStamp = $envelop->last(HandledStamp::class);

        if ($handledStamp instanceof HandledStamp) {
            /** @var CbrRatesDto $rates */
            $rates = $this->serializer->deserialize(
                $handledStamp->getResult(),
                CbrRatesDto::class,
                self::FORMAT_XML
            );

            $rurRate = new CbrRateDto(
                code: CbrRates::BASE_CURRENCY_CODE_DEFAULT,
                nominal: 1,
                value: 1.0,
                vunitRate: 1.0,
            );
            return new CbrRatesDto(
                tradingDate: $rates->tradingDate,
                rates: array_merge($rates->rates, [$rurRate])
            );
        }

        return null;
    }
}
