<?php

namespace Src\Job\Application\Browse;

use Src\Job\Domain\Job;
use Src\Shared\Application\Company\PublicCompanyCard;
use Src\Shared\Application\References\LocationNames;

/**
 * What anyone may see of a live job. The names are in the language of the request. It holds no status, reason, assignee, creator or review detail.
 */
final readonly class PublicJobView
{
    /**
     * @param  array<string, array{id: int, name: string}|null>  $classification  keyed by the API field name (`category`, `role`, `job_type`, ...)
     * @param  array{min: ?int, max: ?int, period: array{id: int, name: string}|null}|null  $salary  null when the salary is hidden or empty
     * @param  list<array{id: int, name: string}>  $skills
     * @param  list<array{id: int, name: string}>  $benefits
     */
    public function __construct(
        public Job $job,
        public PublicCompanyCard $company,
        public array $classification,
        public ?array $salary,
        public LocationNames $location,
        public array $skills,
        public array $benefits,
    ) {}
}
