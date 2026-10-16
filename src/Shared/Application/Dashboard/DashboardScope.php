<?php

namespace Src\Shared\Application\Dashboard;

/**
 * What a card may look at. For a job seeker `userId` is their account; for a company member it is their account and `companyId` their company; for an admin it is the admin account.
 * `jobIds` limits a company member to some jobs of the company: null for a member who sees every job (`jobs.view_all`), the assigned jobs for any other (possibly none).
 * `permissions` holds the admin permissions the admin has among the ones the cards ask for.
 */
final readonly class DashboardScope
{
    /**
     * @param  list<int>|null  $jobIds
     * @param  list<string>  $permissions
     * @param  list<int>  $boundJobIds  the jobs where the member is a hiring manager bound to the stage boundary: only applications that reached it count there
     */
    public function __construct(
        public DashboardAudience $audience,
        public int $userId,
        public ?int $companyId = null,
        public ?array $jobIds = null,
        public array $permissions = [],
        public array $boundJobIds = [],
    ) {}

    public function isRestrictedToJobs(): bool
    {
        return $this->jobIds !== null;
    }
}
