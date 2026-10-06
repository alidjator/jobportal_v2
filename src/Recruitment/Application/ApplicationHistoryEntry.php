<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\StageMoveActor;
use Src\Recruitment\Domain\StageType;

/** One step of an application's history as the applicant sees it: the stage type reached, when, and who moved it. No company stage names, reasons or notes. */
final readonly class ApplicationHistoryEntry
{
    public function __construct(
        public StageType $status,
        public DateTimeImmutable $at,
        public StageMoveActor $by,
    ) {}
}
