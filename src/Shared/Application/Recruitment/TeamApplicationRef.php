<?php

namespace Src\Shared\Application\Recruitment;

/** An application a company member may see, with the facts other contexts need: where it belongs, whether it sits in an assessment stage whether its job was taken down and whether the member reaches it only through an interview panel (the reduced view); `stageId` is the stage it is in. */
final readonly class TeamApplicationRef
{
    public function __construct(
        public int $applicationId,
        public int $companyId,
        public int $jobId,
        public int $applicantUserId,
        public bool $inAssessmentStage,
        public bool $jobTakenDown,
        public bool $panelOnly = false,
        public ?int $stageId = null,
    ) {}
}
