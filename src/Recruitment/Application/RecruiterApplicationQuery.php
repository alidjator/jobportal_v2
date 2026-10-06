<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\StageType;

/** Which applications of one job to list, and in which order. `$search` is a piece of the applicant's name (any letter case). */
final readonly class RecruiterApplicationQuery
{
    public function __construct(
        public int $jobId,
        public ?int $stageId = null,
        public ?StageType $stageType = null,
        public ?bool $meetsMustHave = null,
        public ?string $search = null,
        public bool $newestFirst = true,
    ) {}
}
