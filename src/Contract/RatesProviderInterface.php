<?php

namespace App\Contract;

use App\Dto\CbrRates\CbrRatesDto;
use DateTimeImmutable;

interface RatesProviderInterface
{
    public function getDailyByDate(DateTimeImmutable $date): ?CbrRatesDto;
}
