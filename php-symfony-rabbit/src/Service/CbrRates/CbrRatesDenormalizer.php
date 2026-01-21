<?php

declare(strict_types=1);

namespace App\Service\CbrRates;

use App\Config\CbrRates;
use App\Dto\CbrRates\CbrRateDto;
use App\Dto\CbrRates\CbrRatesDto;
use DateTimeImmutable;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class CbrRatesDenormalizer implements DenormalizerInterface
{
    public const DATE = '@Date';
    public const DATE_FORMAT = 'd.m.Y';
    public const VALUTE = 'Valute';
    public const CODE = 'CharCode';
    public const NOMINAL = 'Nominal';
    public const VALUE = 'Value';
    public const VUNIT_RATE = 'VunitRate';

    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): CbrRatesDto
    {
        return new CbrRatesDto(
            DateTimeImmutable::createFromFormat(self::DATE_FORMAT, $data[self::DATE]),
            $this->denormalizeRates($data[self::VALUTE])
        );
    }

    /**
     * @return CbrRateDto[]
     */
    private function denormalizeRates(array $rates): array
    {
        return array_map(function ($rate) {
            return new CbrRateDto(
                $rate[self::CODE],
                (int)$rate[self::NOMINAL],
                round((float)str_replace(',', '.', $rate[self::VALUE]), CbrRates::CURRENCY_VALUE_PRECISION),
                round((float)str_replace(',', '.', $rate[self::VUNIT_RATE]), CbrRates::CURRENCY_VALUE_PRECISION),
            );
        }, $rates);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return CbrRatesDto::class === $type;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            CbrRatesDto::class => true,
        ];
    }
}
