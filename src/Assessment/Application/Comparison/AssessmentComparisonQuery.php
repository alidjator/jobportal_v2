<?php

namespace Src\Assessment\Application\Comparison;

/**
 * Which applications of a job and which test to compare. `$stageType` filters the applications (the active ones when null), `$stageId` and `$testId` choose which submitted
 * test counts for an application: the one sent in that assessment stage, the one of that test.
 */
final readonly class AssessmentComparisonQuery
{
    public const string SORT_PERCENTAGE = 'percentage';

    public const string SORT_SUBMITTED_AT = 'submitted_at';

    public const string SORT_MATCH_SCORE = 'match_score';

    public function __construct(
        public int $jobId,
        public ?string $stageType = null,
        public ?int $stageId = null,
        public ?int $testId = null,
        public string $sort = self::SORT_PERCENTAGE,
    ) {}
}
