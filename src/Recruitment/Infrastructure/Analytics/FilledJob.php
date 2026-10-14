<?php

namespace Src\Recruitment\Infrastructure\Analytics;

use DateTimeImmutable;
use Src\Shared\Application\Job\PublishedJob;

/** A job whose positions are filled: `days` run from its publication to the hire that filled the last position. */
final readonly class FilledJob
{
    public function __construct(
        public PublishedJob $job,
        public DateTimeImmutable $filledAt,
        public float $days,
        public int $hires,
    ) {}
}
