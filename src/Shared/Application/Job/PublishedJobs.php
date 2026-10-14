<?php

namespace Src\Shared\Application\Job;

/** The jobs that went live at least once, for reports that measure what happened after. Provided by Job. */
interface PublishedJobs
{
    /**
     * The published jobs among these, whatever their status, keyed by job id; a job never published, or missing, is left out.
     *
     * @param  list<int>  $jobIds
     * @return array<int, PublishedJob>
     */
    public function among(array $jobIds): array;

    /**
     * The jobs of the company that are live now (status `active`) and were published.
     *
     * @return list<PublishedJob>
     */
    public function liveOfCompany(int $companyId): array;
}
