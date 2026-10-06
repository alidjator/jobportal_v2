<?php

namespace Src\Recruitment\Application;

/**
 * Whether a question can no longer be changed because it matters to applications: it is attached to a job that has applications, or it has answers.
 * Applications and answers arrive in step 7; until then nothing is locked this way.
 */
interface ScreeningQuestionUsage
{
    public function isLocked(int $questionId): bool;
}
