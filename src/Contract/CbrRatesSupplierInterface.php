<?php

namespace App\Contract;

use App\Dto\CbrRates\CbrRatesDto;

interface CbrRatesSupplierInterface
{
    public function getDailyByDate(\DateTimeImmutable $date): ?CbrRatesDto;
}
