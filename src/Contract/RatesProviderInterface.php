<?php

namespace App\Contract;

use App\Dto\CbrRates\CbrRatesDto;

interface RatesProviderInterface
{
    public function getDailyByDate(\DateTimeImmutable $date): ?CbrRatesDto;
}
