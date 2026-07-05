<?php

declare(strict_types=1);

namespace App\Service\CbrRates;

use App\Dto\CbrRates\CbrRatesDto;
use App\Exception\CbrRates\ParseRatesException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;

class XmlRateParser
{
    private const string FORMAT_XML = 'xml';

    public function __construct(
        private readonly SerializerInterface $serializer,
    ) {
    }

    /**
     * @throws ParseRatesException
     */
    public function parse(string $xml): CbrRatesDto
    {
        try {
            /** @var CbrRatesDto $rates */
            $rates = $this->serializer->deserialize($xml, CbrRatesDto::class, self::FORMAT_XML);
        } catch (ExceptionInterface $e) {
            throw new ParseRatesException('Failed to parse CBR rates XML: '.$e->getMessage(), 0, $e);
        }

        return $rates;
    }
}
