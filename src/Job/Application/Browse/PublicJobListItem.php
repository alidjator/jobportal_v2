<?php

namespace Src\Job\Application\Browse;

use DateTimeImmutable;
use Src\Job\Domain\WorkMode;
use Src\Shared\Application\Company\PublicCompanyCard;

/** One row of the public job search, in the language of the request. The salary is null when the company hides it or gave none. */
final readonly class PublicJobListItem
{
    /**
     * @param  array{id: int, name: string}|null  $jobType
     * @param  array{id: int, name: string}|null  $jobLevel
     * @param  array{min: ?int, max: ?int, period: array{id: int, name: string}|null}|null  $salary
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
        public PublicCompanyCard $company,
        public ?string $city,
        public ?string $province,
        public ?array $jobType,
        public ?array $jobLevel,
        public ?WorkMode $workMode,
        public ?array $salary,
        public ?DateTimeImmutable $deadline,
        public ?DateTimeImmutable $publishedAt,
    ) {}
}
