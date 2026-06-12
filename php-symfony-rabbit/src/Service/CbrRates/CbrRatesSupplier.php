<?php

namespace App\Service\CbrRates;

use App\Config\CbrRates;
use App\Contract\CbrRatesSupplierInterface;
use App\Dto\CbrRates\CbrRateDto;
use App\Dto\CbrRates\CbrRatesDto;
use DateTimeImmutable;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

class CbrRatesSupplier implements CbrRatesSupplierInterface
{
    private const string FORMAT_XML = 'xml';

    public function __construct(
        private readonly SerializerInterface $serializer,
        private readonly CbrHttpClient       $cbrHttpClient,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function getDailyByDate(DateTimeImmutable $date): ?CbrRatesDto
    {
        $xml = $this->cbrHttpClient->getDailyXmlByDate($date);

        /** @var CbrRatesDto $rates */
        $rates = $this->serializer->deserialize(
            $xml,
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
}
