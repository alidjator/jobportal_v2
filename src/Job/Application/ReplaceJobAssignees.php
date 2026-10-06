<?php

namespace Src\Job\Application;

use Src\Job\Domain\Events\JobAssigneesChanged;
use Src\Job\Domain\JobAssignmentRepository;
use Src\Job\Domain\JobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * A company admin sets who works on a job: the whole set is replaced, it holds at least one member, and every member must belong to the company (an
 * admin or a recruiter). Nothing happens, and nothing is audited, when the set does not change. The job row is locked, so two changes run one after the other.
 */
final readonly class ReplaceJobAssignees
{
    public function __construct(
        private JobAccess $access,
        private JobRepository $jobs,
        private JobAssignmentRepository $assignments,
        private CompanyTeam $team,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  list<int>  $userIds
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-admin-required`
     * @throws EntityNotFound when the job does not exist in the company
     * @throws BusinessRuleViolation `invalid-assignees` with `errors` on `user_ids`
     */
    public function execute(Actor $actor, int $jobId, array $userIds, ?string $ipAddress, ?string $userAgent): void
    {
        $member = $this->access->member($actor);
        $this->access->requireCompanyAdmin($member);

        $wanted = array_values(array_unique($userIds));
        $now = $this->clock->now();

        $changed = $this->transactions->run(function () use ($member, $jobId, $wanted, $now): ?array {
            $this->jobs->lock($jobId);
            $this->access->job($member, $jobId);

            $this->assertMembers($member->companyId, $wanted);

            $current = $this->assignments->userIds($jobId);
            $added = array_values(array_diff($wanted, $current));
            $removed = array_values(array_diff($current, $wanted));

            if ($added === [] && $removed === []) {
                return null;
            }

            foreach ($added as $userId) {
                $this->assignments->add($jobId, $userId, $member->userId, $now);
            }

            foreach ($removed as $userId) {
                $this->assignments->remove($jobId, $userId);
            }

            return [$added, $removed];
        });

        if ($changed !== null) {
            $this->events->dispatch([new JobAssigneesChanged($jobId, $member->companyId, $member->userId, $changed[0], $changed[1], $ipAddress, $userAgent, $now)]);
        }
    }

    /**
     * @param  list<int>  $userIds
     *
     * @throws BusinessRuleViolation
     */
    private function assertMembers(int $companyId, array $userIds): void
    {
        if ($userIds === []) {
            throw new BusinessRuleViolation('A job needs at least one assignee.', 'invalid-assignees', extensions: ['errors' => ['user_ids' => ['A job needs at least one assignee.']]]);
        }

        $members = $this->team->roles($companyId);
        $strangers = array_values(array_filter($userIds, static fn (int $userId): bool => ! isset($members[$userId])));

        if ($strangers !== []) {
            throw new BusinessRuleViolation(
                'Every assignee must be a member of the company.',
                'invalid-assignees',
                extensions: ['errors' => ['user_ids' => array_map(static fn (int $id): string => "The account {$id} is not a member of the company.", $strangers)]],
            );
        }
    }
}
