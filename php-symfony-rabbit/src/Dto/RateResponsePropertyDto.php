<?php

declare(strict_types=1);

namespace App\Dto;

use App\Config\CbrRates;

final readonly class RateResponsePropertyDto
{
    public function __construct(
        private string $code,
        private float $value,
        private float $valuePrev,
        private float $diff,
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getValue(): float
    {
        return round($this->value, CbrRates::CURRENCY_VALUE_PRECISION);
    }

    public function getValuePrev(): float
    {
        return round($this->valuePrev, CbrRates::CURRENCY_VALUE_PRECISION);
    }

    public function getDiff(): float
    {
        return round($this->diff, CbrRates::CURRENCY_VALUE_PRECISION);
    }

    public function toArray(): array
    {
        return [
            'code' => $this->getCode(),
            'value' => $this->getValue(),
            'valuePrev' => $this->getValuePrev(),
            'diff' => $this->getDiff(),
        ];
    }
}