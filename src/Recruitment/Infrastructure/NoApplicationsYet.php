<?php

namespace Src\Recruitment\Infrastructure;

use Src\Recruitment\Application\JobApplicationCheck;
use Src\Recruitment\Application\ScreeningQuestionUsage;

/** Stands in until applications exist (step 7): no question is locked by applications or answers, and no job has applications. */
final class NoApplicationsYet implements JobApplicationCheck, ScreeningQuestionUsage
{
    public function isLocked(int $questionId): bool
    {
        return false;
    }

    public function hasApplications(int $jobId): bool
    {
        return false;
    }
}
