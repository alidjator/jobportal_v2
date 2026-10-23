<?php

namespace Src\Assessment\Application\Defaults;

use DateTimeImmutable;

/**
 * A default test of a job as the editor shows it. `problem` is the error code of the rule the default breaks now (an archived test, a battery the company lost, an assessor who
 * left the job, a stage that was removed) and null while it works, so the job page can show the broken ones.
 */
final readonly class JobDefaultView
{
    /**
     * @param  list<array{user_id: int, name: string}>  $assessors
     * @param  array{min_percentage: int, to_stage_id: int, to_stage_name: ?string}|null  $advance
     */
    public function __construct(
        public int $stageId,
        public ?string $stageName,
        public int $testId,
        public ?string $testTitle,
        public int $dueDays,
        public array $assessors,
        public ?array $advance,
        public ?string $problem,
        public ?DateTimeImmutable $updatedAt,
    ) {}
}
