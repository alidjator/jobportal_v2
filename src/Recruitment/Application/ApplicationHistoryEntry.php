<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;
use Src\Recruitment\Domain\RejectionReason;
use Src\Recruitment\Domain\StageMoveActor;
use Src\Recruitment\Domain\StageType;
use Src\Recruitment\Domain\WithdrawalReason;

/** One step of an application's history as the applicant sees it: the stage type reached, when, and who moved it, and the reason code of a rejection or a withdrawal. No company stage names or notes. */
final readonly class ApplicationHistoryEntry
{
    public function __construct(
        public StageType $status,
        public DateTimeImmutable $at,
        public StageMoveActor $by,
        public ?RejectionReason $reason = null,
        public ?WithdrawalReason $withdrawalReason = null,
    ) {}
}
