<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\StageType;

/** An application as the pool reads it; `hiringManagerVisible` is whether it carries the marker for hiring managers. */
final readonly class PoolApplicationRow
{
    public function __construct(
        public int $id,
        public int $jobId,
        public StageType $stageType,
        public DateTimeImmutable $appliedAt,
        public bool $hiringManagerVisible,
    ) {}
}
