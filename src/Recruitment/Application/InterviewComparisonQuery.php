<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\StageType;

/**
 * Which applications of a job to compare by their interview results, and how. `$kitId` picks the kit whose questions become the columns (null: the kit used
 * most often among the rows); `$stageId` and `$stageType` limit the applications like the pipeline list does. The sort is always highest first.
 */
final readonly class InterviewComparisonQuery
{
    public const string SORT_SCORE = 'score';

    public const string SORT_YES = 'yes';

    public const string SORT_MATCH_SCORE = 'match_score';

    public function __construct(
        public int $jobId,
        public ?int $stageId = null,
        public ?StageType $stageType = null,
        public ?int $kitId = null,
        public string $sort = self::SORT_SCORE,
    ) {}
}
