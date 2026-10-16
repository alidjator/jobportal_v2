<?php

namespace Src\Job\Domain;

/** One assignment: the account and its role on the job. */
final readonly class JobAssignee
{
    public function __construct(public int $userId, public JobAssignmentRole $role = JobAssignmentRole::Recruiter) {}
}
