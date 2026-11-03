<?php

namespace Src\Shared\Application\Assessment;

use Src\Shared\Application\Actor;

/**
 * The latest submitted test of each application for the review card and the interview comparison, limited to what the viewer may read on the result page of that test. Provided by Assessment. It
 * never throws for an application the viewer may not read: that one is missing from the answer.
 */
interface ApplicationAssessmentSummaries
{
    /**
     * @param  list<int>  $applicationIds
     * @return array<int, AssessmentSummary> by application id; an application without a submitted test, or whose test the viewer may not read, is missing
     */
    public function latestForApplications(Actor $viewer, array $applicationIds): array;
}
