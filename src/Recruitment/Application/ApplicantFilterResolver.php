<?php

namespace Src\Recruitment\Application;

use Src\Shared\Application\Assessment\AssessmentInvitations;
use Src\Shared\Application\References\ReferenceValues;

/**
 * Fills in what the applicant filters cannot carry from the request: the rank of the education level asked for (a higher rank matches too; a level that does not exist
 * matches nothing) and, for `test_pending`, the applications whose test is not finished, asked from the Assessment port for the jobs in scope.
 */
final readonly class ApplicantFilterResolver
{
    /** Above every real rank, so an unknown education level keeps no application. */
    private const int NO_MATCH_RANK = 65_536;

    public function __construct(private ReferenceValues $references, private AssessmentInvitations $assessments) {}

    /** @param  list<int>|null  $jobIds  the jobs in scope (null: every job of the company) */
    public function resolve(int $companyId, ApplicantFilters $filters, ?array $jobIds): ApplicantFilters
    {
        $level = $filters->educationLevelId;

        return $filters->resolved(
            $level === null ? null : ($this->references->educationRanks([$level])[$level] ?? self::NO_MATCH_RANK),
            $filters->wantsTestPending() ? $this->assessments->applicationIdsWithPendingTest($companyId, $jobIds) : null,
        );
    }
}
