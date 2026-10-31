<?php

namespace Src\Shared\Application\Tasks;

use DateTimeImmutable;

/**
 * A context's part of the to-do list of a job seeker. The context registers its sources in `ApplicantTaskSources` from its service provider, so the Dashboard context that serves the list knows no other
 * context. A source reads on indexed columns and returns at most `$limit` open items of the account, and none of an application that is final or whose applicant was anonymised.
 */
interface ApplicantTaskSource
{
    /** @return list<ApplicantTask> */
    public function tasks(int $userId, DateTimeImmutable $now, int $limit): array;
}
