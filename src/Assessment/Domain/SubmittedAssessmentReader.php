<?php

namespace Src\Assessment\Domain;

interface SubmittedAssessmentReader
{
    /**
     * The latest submitted invitation of each application (the newest submission, the highest invitation id among equals), optionally only of the invitations sent in one
     * assessment stage or of one test; an application without one is missing.
     *
     * @param  list<int>  $applicationIds
     * @return array<int, SubmittedAssessment> by application id
     */
    public function latest(array $applicationIds, ?int $stageId = null, ?int $testId = null): array;
}
