<?php

namespace App\Service\CbrRates;

use App\Config\CbrRates;
use App\Contract\CbrRatesSupplierInterface;
use App\Contract\RatesProviderInterface;
use App\Dto\CbrRates\CbrRateDto;
use App\Dto\CbrRates\CbrRatesDto;
use App\Exception\CbrRates\ParseRatesException;
use DateTimeImmutable;

readonly class CbrRatesSupplier implements CbrRatesSupplierInterface, RatesProviderInterface
{
    public function __construct(
        private CbrHttpClient $cbrHttpClient,
        private XmlRateParser $xmlRateParser,
    ) {
    }

    /**
     * @throws ParseRatesException
     */
    public function getDailyByDate(DateTimeImmutable $date): ?CbrRatesDto
    {
        $xml = $this->cbrHttpClient->getDailyXmlByDate($date);

        $rates = $this->xmlRateParser->parse($xml);

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
