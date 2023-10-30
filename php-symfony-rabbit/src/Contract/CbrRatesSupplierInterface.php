<?php

namespace App\Contract;

use App\Dto\CbrRates\CbrRatesDto;
use DateTimeImmutable;

interface CbrRatesSupplierInterface
{
    public function getDailyByDate(DateTimeImmutable $date): ?CbrRatesDto;
}