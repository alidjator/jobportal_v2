<?php

namespace Src\Job\Application\Insight;

use DateTimeImmutable;

/** The monthly salary of a group: the median and the P25 to P75 range of the midpoint of each job's range, in whole rupiah, with how many jobs and companies stand behind them. */
final readonly class SalaryFigures
{
    public function __construct(
        public int $jobs,
        public int $companies,
        public int $p25,
        public int $median,
        public int $p75,
        public DateTimeImmutable $computedAt,
    ) {}
}
