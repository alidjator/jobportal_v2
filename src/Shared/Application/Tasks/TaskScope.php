<?php

namespace Src\Shared\Application\Tasks;

use DateTimeImmutable;

/**
 * Whose task list is being counted: a member of a company, with the jobs they may see (`jobIds`, null for a member who sees every job of the company: `jobs.view_all`, which every
 * company admin holds) and the time of the count.
 */
final readonly class TaskScope
{
    /**
     * `boundJobIds` are the jobs where the member is a hiring manager bound to the stage boundary: on those only the applications that reached the boundary count.
     *
     * @param  list<int>|null  $jobIds
     * @param  list<int>  $boundJobIds
     */
    public function __construct(public int $userId, public int $companyId, public ?array $jobIds, public bool $isCompanyAdmin, public DateTimeImmutable $now, public array $boundJobIds = []) {}
}
