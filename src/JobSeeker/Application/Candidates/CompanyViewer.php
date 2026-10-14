<?php

namespace Src\JobSeeker\Application\Candidates;

/** A member of an active company who searches candidates (holds `candidates.search`); `seesAllJobs` is `jobs.view_all`. */
final readonly class CompanyViewer
{
    public function __construct(public int $memberId, public int $companyId, public bool $seesAllJobs) {}
}
