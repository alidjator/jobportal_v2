<?php

namespace Src\Shared\Application\Applicant;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Adds a PDF to a job seeker's resumes on their behalf (the CV a company sent them, once they choose to keep it). Provided by JobSeeker. */
interface ResumeImports
{
    /**
     * @return int the id of the new resume
     *
     * @throws BusinessRuleViolation `file-too-large` or `invalid-resume`
     * @throws StateConflict `resume-limit-reached`
     */
    public function import(int $userId, string $name, string $contents): int;
}
