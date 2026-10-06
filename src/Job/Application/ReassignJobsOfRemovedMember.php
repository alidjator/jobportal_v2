<?php

namespace Src\Job\Application;

use DateTimeImmutable;
use Src\Job\Domain\Events\JobAssigneesChanged;
use Src\Job\Domain\JobAssignmentRepository;
use Src\Job\Domain\JobRepository;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;

/**
 * A member left the company: their assignments end, and a job left without any assignee is handed to the company admins that remain, so every job
 * keeps a team. Each job is handled in its own transaction with its row locked; running it again changes nothing.
 */
final readonly class ReassignJobsOfRemovedMember
{
    public function __construct(
        private JobRepository $jobs,
        private JobAssignmentRepository $assignments,
        private CompanyTeam $team,
        private TransactionManager $transactions,
        private EventDispatcher $events,
    ) {}

    public function execute(int $companyId, int $userId, DateTimeImmutable $at): void
    {
        foreach ($this->assignments->jobIdsOf($userId) as $jobId) {
            /** @var list<int>|null $added */
            $added = $this->transactions->run(function () use ($companyId, $userId, $jobId, $at): ?array {
                $this->jobs->lock($jobId);
                $job = $this->jobs->find($jobId);

                if ($job === null || $job->companyId !== $companyId) {
                    return null;
                }

                $this->assignments->remove($jobId, $userId);

                if ($this->assignments->userIds($jobId) !== []) {
                    return [];
                }

                $admins = array_keys(array_filter($this->team->roles($companyId), static fn (string $role): bool => $role === 'company_admin'));

                foreach ($admins as $adminId) {
                    $this->assignments->add($jobId, $adminId, null, $at);
                }

                return $admins;
            });

            if ($added !== null) {
                $this->events->dispatch([new JobAssigneesChanged($jobId, $companyId, null, $added, [$userId], null, null, $at)]);
            }
        }
    }
}
