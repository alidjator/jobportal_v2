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
 * A member left the company: their assignments end, and a job left without assignees goes to the company admin the remover picked, or to the owner when
 * none was picked; a job that still has assignees keeps them. A company with no owner (the owner's account was erased) hands such a job to the company
 * admins that remain, so every job keeps a team. Each job is handled in its own transaction with its row locked; running it again changes nothing.
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

    public function execute(int $companyId, int $userId, DateTimeImmutable $at, ?int $transferToUserId = null): void
    {
        $recipient = $transferToUserId ?? $this->team->ownerUserId($companyId);

        foreach ($this->assignments->jobIdsOf($userId) as $jobId) {
            /** @var list<int>|null $added */
            $added = $this->transactions->run(function () use ($companyId, $userId, $jobId, $at, $recipient): ?array {
                $this->jobs->lock($jobId);
                $job = $this->jobs->find($jobId);

                if ($job === null || $job->companyId !== $companyId) {
                    return null;
                }

                $this->assignments->remove($jobId, $userId);

                $remaining = $this->assignments->userIds($jobId);

                if ($remaining !== []) {
                    return [];
                }

                if ($recipient !== null && $recipient !== $userId) {
                    $this->assignments->add($jobId, $recipient, null, $at);

                    return [$recipient];
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
