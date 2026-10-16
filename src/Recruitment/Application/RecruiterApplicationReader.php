<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;

/** Reads applications for the company's team. It checks nothing about who asks: the use cases decide who may see which job. */
interface RecruiterApplicationReader
{
    /** @return Page<RecruiterApplicationRow> */
    public function page(RecruiterApplicationQuery $query, PageRequest $request): Page;

    /** How many applications the query selects (the search and every filter applied, the page ignored). */
    public function count(RecruiterApplicationQuery $query): int;

    /**
     * The first `$limit` applications the query selects, in its order, with the contact details and the screening answers; read in chunks as the caller iterates.
     *
     * @return iterable<ApplicantExportRow>
     */
    public function export(RecruiterApplicationQuery $query, int $limit): iterable;

    /**
     * How many applications each stage of the job holds, keyed by stage id; a stage without applications is missing.
     *
     * @return array<int, int>
     */
    public function countByStage(int $jobId): array;

    public function find(int $id): ?RecruiterApplicationDetailData;

    /**
     * The other applications of the same person to the same company, newest first (every stage, withdrawn and rejected ones included).
     *
     * @return list<OtherApplicationRow>
     */
    public function otherApplications(int $applicationId): array;

    /**
     * How many other active (not rejected or withdrawn) applications of the same person to the same company each application has, keyed by application id; an application whose
     * applicant was anonymised, or without others, is missing. `$visibleJobIds` limits the count to those jobs (null: every job).
     *
     * @param  list<int>  $applicationIds
     * @param  list<int>|null  $visibleJobIds
     * @return array<int, int>
     */
    public function activeOtherCounts(array $applicationIds, ?array $visibleJobIds): array;
}
