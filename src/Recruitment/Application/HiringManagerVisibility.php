<?php

namespace Src\Recruitment\Application;

use DateTimeImmutable;

/**
 * The marker that shows an application to the hiring managers of its job (`docs/design/phase-8-hiring-managers.md` section 2). It is set when the application
 * reaches a stage at or after the stage boundary of the job, or when a hiring manager joins one of its interview panels; it is never cleared.
 */
interface HiringManagerVisibility
{
    /** Sets the marker of the application when its current stage is at or after the boundary of its job. */
    public function refreshApplication(int $applicationId, DateTimeImmutable $at): void;

    /** Sets the marker on every application of the company that qualifies now (after the stages were reordered or merged). */
    public function refreshCompany(int $companyId, DateTimeImmutable $at): void;

    /**
     * Sets the marker of the application when one of these accounts is a hiring manager of its job: a hiring manager on an interview panel sees the application.
     *
     * @param  list<int>  $userIds
     */
    public function showToPanel(int $applicationId, int $jobId, array $userIds, DateTimeImmutable $at): void;

    /** Sets the marker of the application whatever its stage: it was sent for review to the hiring managers of its job (never cleared). */
    public function showForReview(int $applicationId, DateTimeImmutable $at): void;

    public function isVisible(int $applicationId): bool;
}
