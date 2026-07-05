<?php

namespace App\Domain\Calendar;

use App\Contract\RatesProviderInterface;
use App\Exception\CbrRates\PreviousTradingDayNotFoundException;

readonly class PreviousTradingDayResolver
{
    private const int MAX_LOOKBACK_DAYS = 15;

    public function __construct(
        private RatesProviderInterface $ratesProvider,
    ) {
    }

    /**
     * Returns the most recent previous date for which a daily snapshot is available,
     * going back up to MAX_LOOKBACK_DAYS days from the given date.
     *
     * @throws PreviousTradingDayNotFoundException|\DateMalformedStringException
     */
    public function resolve(\DateTimeImmutable $date): \DateTimeImmutable
    {
        for ($i = 1; $i <= self::MAX_LOOKBACK_DAYS; ++$i) {
            $candidate = $date->modify(sprintf('-%d day', $i));

            if (null !== $this->ratesProvider->getDailyByDate($candidate)) {
                return $candidate;
            }
        }

        throw new PreviousTradingDayNotFoundException();
    }
}
