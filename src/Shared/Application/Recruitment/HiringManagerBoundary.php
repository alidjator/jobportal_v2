<?php

namespace Src\Shared\Application\Recruitment;

/**
 * What the stage boundary of a job needs from Recruitment (`docs/design/phase-8-hiring-managers.md` section 2). Provided by Recruitment.
 */
interface HiringManagerBoundary
{
    /** Whether the stage belongs to the company and is one a boundary may name: an active stage, not a final one (`rejected`, `withdrawn`). */
    public function isBoundaryStage(int $companyId, int $stageId): bool;

    /** Shows the applications of the job that now sit at or after its boundary to its hiring managers; nothing is ever hidden again. */
    public function reevaluateJob(int $jobId): void;
}
