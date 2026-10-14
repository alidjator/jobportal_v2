<?php

namespace Src\Recruitment\Infrastructure\Analytics;

use Src\Shared\Application\Job\PublishedJob;

/** A live job whose positions are not all filled yet. */
final readonly class OpenJob
{
    public function __construct(
        public PublishedJob $job,
        public float $daysOpen,
        public int $hires,
    ) {}
}
