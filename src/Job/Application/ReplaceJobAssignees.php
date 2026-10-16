<?php

namespace Src\Job\Application;

use Src\Job\Domain\Events\JobAssigneesChanged;
use Src\Job\Domain\JobAssignmentRepository;
use Src\Job\Domain\JobAssignmentRole;
use Src\Job\Domain\JobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * A company admin sets who works on a job: the whole set is replaced, it holds at least one member, and every member must belong to the company (an
 * admin or a recruiter), and each has a role on the job (recruiter or hiring manager). Nothing happens, and nothing is audited, when the set does not change. The job row is locked, so two changes run one after the other.
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
     * @param  array<int, ?JobAssignmentRole>  $assignees  the wanted roles by account id; null keeps the role of an assignee that stays (a new one is a recruiter)
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound when the job does not exist in the company
     * @throws BusinessRuleViolation `invalid-assignees` with `errors` on `user_ids`
     */
    public function execute(Actor $actor, int $jobId, array $assignees, ?string $ipAddress, ?string $userAgent): void
    {
        $member = $this->access->member($actor);
        $this->access->require($member, CompanyPermission::JobsAssign);

        $now = $this->clock->now();

        $changed = $this->transactions->run(function () use ($member, $jobId, $assignees, $now): ?array {
            $this->jobs->lock($jobId);
            $this->access->job($member, $jobId);

            $this->assertMembers($member->companyId, array_keys($assignees));

            $current = [];

            foreach ($this->assignments->assignees($jobId) as $assignee) {
                $current[$assignee->userId] = $assignee->role;
            }

            $wanted = [];

            foreach ($assignees as $userId => $role) {
                $wanted[$userId] = $role ?? $current[$userId] ?? JobAssignmentRole::Recruiter;
            }

            $added = array_values(array_diff(array_keys($wanted), array_keys($current)));
            $removed = array_values(array_diff(array_keys($current), array_keys($wanted)));
            $rolesChanged = array_values(array_filter(
                array_intersect(array_keys($wanted), array_keys($current)),
                static fn (int $userId): bool => $wanted[$userId] !== $current[$userId],
            ));

            if ($added === [] && $removed === [] && $rolesChanged === []) {
                return null;
            }

            foreach ($added as $userId) {
                $this->assignments->add($jobId, $userId, $member->userId, $now, $wanted[$userId]);
            }

            foreach ($removed as $userId) {
                $this->assignments->remove($jobId, $userId);
            }

            foreach ($rolesChanged as $userId) {
                $this->assignments->changeRole($jobId, $userId, $wanted[$userId]);
            }

            return [$added, $removed, $rolesChanged];
        });

        if ($changed !== null) {
            $this->events->dispatch([new JobAssigneesChanged($jobId, $member->companyId, $member->userId, $changed[0], $changed[1], $changed[2], $ipAddress, $userAgent, $now)]);
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
