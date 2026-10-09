<?php

namespace Src\Job\Application\Views;

use DateInterval;
use Src\Job\Application\JakartaDate;
use Src\Shared\Application\Clock;

/**
 * Keeps the raw view log for 90 days: older days are folded into the daily counts of each job and the raw rows are deleted, so the long-term trend stays without
 * any trace of who looked. Safe to run again.
 */
final readonly class FoldJobViews
{
    public const int KEEP_DAYS = 90;

    public function __construct(private JobViewLog $log, private Clock $clock) {}

    /** @return int how many raw rows were removed */
    public function execute(): int
    {
        $before = JakartaDate::today($this->clock->now())->sub(new DateInterval('P'.self::KEEP_DAYS.'D'))->format('Y-m-d');

        return $this->log->foldBefore($before);
    }
}
