<?php

namespace Src\Job\Application\Browse;

use DateTimeImmutable;
use Src\Job\Domain\JobWorkPlace;
use Src\Job\Domain\WorkMode;

/** One matching job as the reader returns it: ids only, for the use case to turn into names and the company card. `places` are the company locations of the job, the main one first. */
final readonly class JobSearchHit
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
        public int $companyId,
        public ?int $provinceId,
        public ?int $cityId,
        public ?int $jobTypeId,
        public ?int $jobLevelId,
        public ?WorkMode $workMode,
        public ?int $salaryMin,
        public ?int $salaryMax,
        public ?int $salaryPeriodId,
        public bool $salaryVisible,
        public ?DateTimeImmutable $deadline,
        public ?DateTimeImmutable $publishedAt,
        public ?string $summary = null,
        public ?string $salaryNote = null,
        /** @var list<JobWorkPlace> */
        public array $places = [],
    ) {}
}
