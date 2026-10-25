<?php

namespace Src\Recruitment\Application;

/** An interview for the team with what its page needs besides it: the job and the candidate. `candidateName` is null when the profile kept no name. */
final readonly class CompanyInterviewDetail
{
    public function __construct(
        public InterviewView $view,
        public int $jobId,
        public ?string $jobTitle,
        public ?string $candidateName,
    ) {}
}
