<?php

namespace Src\Recruitment\Application;

/**
 * One application of a comparison with its latest scored interview. `$result` and `$cells` are null for a locked row (the viewer is on the panel and has not
 * sent their scorecard). A cell holds the average of one kit question, null with a count of 0 when the interview has no matching scored question.
 */
final readonly class InterviewComparisonRow
{
    /** @param  list<QuestionScore>|null  $cells  one per column, in the order of the columns */
    public function __construct(
        public RecruiterApplicationRow $application,
        public LatestInterview $interview,
        public int $panelSize,
        public ?array $cells,
    ) {}
}
