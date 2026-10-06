<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\RejectionReason;
use Src\Recruitment\Domain\StageMoveActor;
use Src\Recruitment\Domain\StageType;

/** One step of an application's history as the company's team sees it: both stages (ids and types), who moved it, and the rejection reason and note. */
final readonly class RecruiterHistoryEntry
{
    public function __construct(
        public ?int $fromStageId,
        public int $toStageId,
        public ?StageType $fromType,
        public StageType $toType,
        public StageMoveActor $by,
        public ?int $actorId,
        public ?RejectionReason $reason,
        public ?string $note,
        public DateTimeImmutable $at,
    ) {}
}
