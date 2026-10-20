<?php

namespace Src\LegacyImport\Application;

/** A legacy job as the new schema holds it: values already checked and mapped, ready to be written. */
final readonly class NewJob
{
    /**
     * @param  list<int>  $skillIds
     * @param  list<int>  $benefitIds
     */
    public function __construct(
        public int $companyId,
        public int $creatorUserId,
        public string $title,
        public string $slug,
        public string $status,
        public ?string $description,
        public ?int $categoryId,
        public ?int $roleId,
        public ?int $jobTypeId,
        public ?int $jobLevelId,
        public ?int $workTimeId,
        public ?int $experienceLevelId,
        public ?int $educationLevelId,
        public ?int $vacancies,
        public ?string $deadline,
        public ?int $salaryMin,
        public ?int $salaryMax,
        public ?int $salaryPeriodId,
        public bool $salaryVisible,
        public string $workMode,
        public ResolvedLocation $location,
        public ?string $addressLine,
        public array $skillIds,
        public array $benefitIds,
        public ?string $publishedAt,
        public string $createdAt,
        public string $updatedAt,
        public string $migratedAt,
    ) {}
}
