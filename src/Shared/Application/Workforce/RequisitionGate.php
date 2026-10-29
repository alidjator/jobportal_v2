<?php

namespace Src\Shared\Application\Workforce;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/** What the Job context asks of the Workforce context when a job is opened or its vacancies change (`docs/design/erp-requisitions.md` sections 1 and 3). Provided by Workforce. */
interface RequisitionGate
{
    /** Whether a job of this company must be opened from a requisition: an internal tenant whose setting `recruitment.requisition_required` is on. */
    public function isRequired(int $companyId): bool;

    /**
     * The requisitions of the company a job may still be opened for, newest first: approved or in recruitment, with hires left that its other jobs have not taken (none for a tenant that is not an internal one).
     *
     * @return list<OpenRequisition>
     */
    public function open(int $companyId): array;

    /**
     * Reserves vacancies of the requisition for a job, in the caller's transaction: the requisition is one of the tenant's, approved or already in recruitment, and the vacancies of its other jobs
     * plus these do not exceed its hires. The requisition moves to `in_recruitment`. `$exceptJobId` is the job whose own vacancies are being changed.
     *
     * @throws BusinessRuleViolation `invalid-requisition` or `requisition-vacancies-exceeded`
     * @throws StateConflict `requisition-not-open`
     */
    public function claim(int $companyId, int $requisitionId, int $vacancies, ?int $exceptJobId = null): RequisitionTerms;
}
