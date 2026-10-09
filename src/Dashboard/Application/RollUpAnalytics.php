<?php

namespace Src\Dashboard\Application;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Src\Shared\Application\Analytics\AnalyticsRollups;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * Rebuilds the daily summaries of every context for a range of Jakarta days. Without a range it rebuilds yesterday. A day is rebuilt from the source tables, so a day
 * can be rebuilt any number of times, and the days before the summaries existed are filled by asking for the whole history (`from` null with `backfill`).
 */
final readonly class RollUpAnalytics
{
    public function __construct(private AnalyticsRollups $rollups, private Clock $clock) {}

    /**
     * @return int how many days were rebuilt
     *
     * @throws BusinessRuleViolation `invalid-range`
     */
    public function execute(?string $from, ?string $to, bool $backfill = false): int
    {
        $zone = new DateTimeZone('Asia/Jakarta');
        $yesterday = $this->clock->now()->setTimezone($zone)->setTime(0, 0)->sub(new DateInterval('P1D'));
        $to ??= $yesterday->format('Y-m-d');

        if ($to > $yesterday->format('Y-m-d')) {
            throw new BusinessRuleViolation('Only days up to yesterday can be summarised.', 'invalid-range');
        }

        $days = 0;

        foreach ($this->rollups->all() as $rollup) {
            $start = $from ?? ($backfill ? $rollup->earliestDay() : $to);

            if ($start === null || $start > $to) {
                continue;
            }

            for ($day = new DateTimeImmutable($start, $zone), $end = new DateTimeImmutable($to, $zone); $day <= $end; $day = $day->add(new DateInterval('P1D'))) {
                $rollup->rebuild($day->format('Y-m-d'));
                $days++;
            }
        }

        return $days;
    }
}
