<?php

namespace App\Contract;

use App\Dto\CbrRatesDto;
use DateTimeImmutable;

interface CbrRatesSupplierInterface
{
    public function getDailyByDate(DateTimeImmutable $date): ?CbrRatesDto;
}