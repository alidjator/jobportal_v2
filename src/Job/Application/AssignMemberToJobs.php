<?php

namespace Src\Job\Application;

use Src\Job\Domain\Events\JobAssigneesChanged;
use Src\Job\Domain\Events\MemberAssignedToJobs;
use Src\Job\Domain\JobAssignmentRepository;
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
use Src\Shared\Domain\Exceptions\ErrorMessage;

/**
 * A company admin adds one member to many jobs of the company at once (the member page): the member is added as a recruiter, the assignees a job already has stay
 * and keep their roles, and a job that already has the member is only counted. Every id must be a job of the company (any status, imported jobs included), or nothing is
 * written. The jobs are locked in ascending id so two requests cannot deadlock. Each job still announces its change (the audit stays per job); the member gets one notice.
 */
final readonly class AssignMemberToJobs
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
     * @param  list<int>  $jobIds  in the order the caller sent them; duplicates collapse
     * @return array{added: int, already: int}
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound when the account is not a member of the company
     * @throws BusinessRuleViolation `invalid-assignments` with `errors` on `job_ids.<position>`
     */
    public function execute(Actor $actor, int $userId, array $jobIds, ?string $ipAddress, ?string $userAgent): array
    {
        $member = $this->access->member($actor);
        $this->access->require($member, CompanyPermission::JobsAssign);

        if (! isset($this->team->roles($member->companyId)[$userId])) {
            throw EntityNotFound::of('Member', $userId);
        }

        $now = $this->clock->now();

        $result = $this->transactions->run(function () use ($member, $userId, $jobIds, $now): array {
            $unique = array_values(array_unique($jobIds));
            sort($unique);

            foreach ($unique as $jobId) {
                $this->jobs->lock($jobId);
            }

            $errors = [];

            foreach ($jobIds as $position => $jobId) {
                if ($this->jobs->find($jobId)?->companyId !== $member->companyId) {
                    $errors["job_ids.{$position}"] = [new ErrorMessage('The job :job does not belong to the company.', ['job' => $jobId])];
                }
            }

            if ($errors !== []) {
                throw new BusinessRuleViolation('Every job must belong to the company.', 'invalid-assignments', extensions: ['errors' => $errors]);
            }

            $added = [];
            $already = 0;

            foreach ($unique as $jobId) {
                if (in_array($userId, $this->assignments->userIds($jobId), true)) {
                    $already++;

                    continue;
                }

                $this->assignments->add($jobId, $userId, $member->userId, $now);
                $added[] = $jobId;
            }

            return ['added' => $added, 'already' => $already];
        });

        foreach ($result['added'] as $jobId) {
            $this->events->dispatch([new JobAssigneesChanged($jobId, $member->companyId, $member->userId, [$userId], [], [], $ipAddress, $userAgent, $now, bulk: true)]);
        }

        if ($result['added'] !== []) {
            $this->events->dispatch([new MemberAssignedToJobs($member->companyId, $userId, $member->userId, count($result['added']), $ipAddress, $userAgent, $now)]);
        }

        return ['added' => count($result['added']), 'already' => $result['already']];
    }
}
