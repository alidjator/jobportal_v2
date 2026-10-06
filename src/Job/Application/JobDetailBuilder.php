<?php

namespace Src\Job\Application;

use Src\Job\Domain\Job;
use Src\Job\Domain\JobAssignmentRepository;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\References\ReferenceValues;

/** Adds what a client needs to show a job: the names of its skills and benefits in the request language, and its assignees with their name and role. */
final readonly class JobDetailBuilder
{
    public function __construct(
        private ReferenceValues $references,
        private JobAssignmentRepository $assignments,
        private CompanyTeam $team,
        private CompanyMemberAccounts $accounts,
    ) {}

    public function build(Job $job, string $language): JobDetail
    {
        return new JobDetail(
            $job,
            $this->named('skills', $job->content->skillIds, $language),
            $this->named('benefits', $job->content->benefitIds, $language),
            $this->assignees($job),
        );
    }

    /** @return list<array{user_id: int, name: string, role: string}> */
    public function assignees(Job $job): array
    {
        $userIds = $this->assignments->userIds((int) $job->id);
        $roles = $this->team->roles($job->companyId);
        $accounts = $this->accounts->summaries($userIds);

        return array_map(
            static fn (int $userId): array => [
                'user_id' => $userId,
                'name' => isset($accounts[$userId]) ? $accounts[$userId]->name : '',
                'role' => $roles[$userId] ?? '',
            ],
            $userIds,
        );
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, name: string}>
     */
    private function named(string $master, array $ids, string $language): array
    {
        return array_map(
            fn (int $id): array => ['id' => $id, 'name' => (string) $this->references->name($master, $id, $language)],
            $ids,
        );
    }
}
