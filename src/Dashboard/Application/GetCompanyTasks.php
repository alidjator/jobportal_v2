<?php

namespace Src\Dashboard\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Tasks\TaskProvider;
use Src\Shared\Application\Tasks\TaskProviders;
use Src\Shared\Application\Tasks\TaskScope;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * What waits for a company member (`docs/design/phase-8-recruiter-workday.md` section 1): one `{key, count, filter}` item per task the member may use, counting only the jobs they may
 * see (every job for `jobs.view_all`, the assigned jobs for anyone else). Computed live, kept for 60 seconds per member.
 */
final readonly class GetCompanyTasks
{
    private const int CACHE_SECONDS = 60;

    public function __construct(private TaskProviders $providers, private CompanyDirectory $companies, private JobDirectory $jobs, private DashboardCache $cache, private Clock $clock) {}

    /**
     * @return array{generated_at: string, items: list<array{key: string, count: int, filter: string}>}
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     */
    public function execute(Actor $actor): array
    {
        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null || $actor->id === null) {
            throw new AccessDenied('Only a company member has a task list.', 'not-a-company-member');
        }

        $companyId = $actor->membership->companyId;

        if (! $this->companies->isActive($companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        $userId = $actor->id;
        $permissions = $actor->membership->effectivePermissions();
        $bound = $actor->can(CompanyPermission::JobsViewAll) ? [] : $this->jobs->hiringManagerJobIds($companyId, $userId);

        return $this->cache->remember("tasks:company:{$companyId}:member:{$userId}:".md5(implode(',', $permissions).'|'.implode(',', $bound)), self::CACHE_SECONDS, function () use ($actor, $companyId, $userId, $permissions, $bound): array {
            $now = $this->clock->now();
            $scope = new TaskScope($userId, $companyId, $actor->can(CompanyPermission::JobsViewAll) ? null : $this->jobs->assignedJobIds($companyId, $userId), $actor->isCompanyAdmin(), $now, $bound);

            $items = array_map(
                static fn (TaskProvider $provider): array => ['key' => $provider->key(), 'count' => $provider->count($scope), 'filter' => $provider->filter($scope)],
                array_values(array_filter($this->providers->all(), static fn (TaskProvider $provider): bool => $provider->permission() === null || in_array($provider->permission(), $permissions, true))),
            );

            return ['generated_at' => $now->format(DATE_ATOM), 'items' => $items];
        });
    }
}
