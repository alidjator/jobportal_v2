<?php

namespace Src\Recruitment\Domain;

use DateTimeImmutable;

/** A named, ordered set of library questions with their per-job flags, owned by a company. Applying it to a job copies its content; nothing points back to it. */
final readonly class ScreeningPreset
{
    /** @param  list<JobQuestion>  $questions  in their order */
    public function __construct(
        public int $id,
        public int $companyId,
        public string $name,
        public ?int $jobLevelId,
        public array $questions,
        public int $createdByUserId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
