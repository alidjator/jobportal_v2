<?php

namespace Src\Shared\Application\Calendar;

use DateTimeImmutable;

/**
 * What a source may read for one request: the company and the member, the jobs the member may see (`jobIds`, null for a member who sees every job) and the jobs where
 * the member is a hiring manager bound to the stage boundary (`boundJobIds`: only applications that reached it count there), the window `[from, to)` in UTC, the types asked
 * for, an optional job, `mine` (only what involves the member), the member's permissions (for the actions) and the most events a source returns.
 */
final readonly class CalendarScope
{
    /**
     * @param  list<int>|null  $jobIds
     * @param  list<int>  $boundJobIds
     * @param  list<string>  $types
     * @param  list<string>  $permissions
     */
    public function __construct(
        public int $userId,
        public int $companyId,
        public ?array $jobIds,
        public array $boundJobIds,
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public array $types,
        public ?int $jobId,
        public bool $mine,
        public array $permissions,
        public DateTimeImmutable $now,
        public int $limit,
    ) {}

    public function wants(string $type): bool
    {
        return in_array($type, $this->types, true);
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}
