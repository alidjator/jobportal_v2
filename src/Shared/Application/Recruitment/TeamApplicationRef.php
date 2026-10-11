<?php

namespace Src\Shared\Application\Recruitment;

/** An application a company member may see, with the facts other contexts need: where it belongs, whether it sits in an assessment stage and whether its job was taken down. */
final readonly class TeamApplicationRef
{
    public function __construct(
        public int $applicationId,
        public int $companyId,
        public int $jobId,
        public int $applicantUserId,
        public bool $inAssessmentStage,
        public bool $jobTakenDown,
    ) {}
}
