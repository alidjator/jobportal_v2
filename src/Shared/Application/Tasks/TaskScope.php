<?php

namespace Src\Shared\Application\Tasks;

use DateTimeImmutable;

/**
 * Whose task list is being counted: a member of a company, with the jobs they may see (`jobIds`, null for a member who sees every job of the company: `jobs.view_all`, which every
 * company admin holds) and the time of the count.
 */
final readonly class TaskScope
{
    /** @param  list<int>|null  $jobIds */
    public function __construct(public int $userId, public int $companyId, public ?array $jobIds, public bool $isCompanyAdmin, public DateTimeImmutable $now) {}
}
