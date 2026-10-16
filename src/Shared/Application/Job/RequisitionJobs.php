<?php

namespace Src\Shared\Application\Job;

/** The jobs opened for a requisition, for the Workforce context that counts their vacancies. Provided by Job. */
interface RequisitionJobs
{
    /** The vacancies of the requisition's jobs that are not closed or expired, without the job `$exceptJobId`. */
    public function openVacancies(int $requisitionId, ?int $exceptJobId = null): int;

    /** How many jobs, in any status, were opened for the requisition. */
    public function count(int $requisitionId): int;
}
