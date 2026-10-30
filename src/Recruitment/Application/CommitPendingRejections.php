<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\PendingRejection;
use Src\Recruitment\Domain\PendingRejectionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyMembershipResolver;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Applies the rejections whose undo window has passed (`docs/design/phase-8-rejections.md` section 2), one by one, as the member who asked for them: the normal rejection with its history,
 * notice and email. A rejection the team undid meanwhile is gone before it is claimed. One whose application left its stage meanwhile (the job seeker withdrew or accepted an offer, the job was taken
 * down) or whose requester no longer holds the permission is dropped, and the requester gets the in-app notice `rejection.dropped`.
 */
final readonly class CommitPendingRejections
{
    public const int BATCH = 200;

    public function __construct(
        private PendingRejectionRepository $pending,
        private ProcessApplication $process,
        private ApplicationRepository $applications,
        private CompanyMembershipResolver $memberships,
        private InAppNotifier $notifier,
        private JobDirectory $jobs,
        private Clock $clock,
    ) {}

    /** @return array{committed: int, dropped: int} */
    public function execute(): array
    {
        $committed = 0;
        $dropped = 0;

        foreach ($this->pending->due($this->clock->now(), self::BATCH) as $row) {
            if (! $this->pending->claim((int) $row->id)) {
                continue;
            }

            if ($this->commit($row)) {
                $committed++;
            } else {
                $dropped++;
                $this->tell($row);
            }
        }

        return ['committed' => $committed, 'dropped' => $dropped];
    }

    private function commit(PendingRejection $row): bool
    {
        $application = $this->applications->find($row->applicationId);

        if ($application === null || $application->stageId !== $row->stageId) {
            return false;
        }

        $actor = Actor::companyMember($row->requestedByUserId)->withMembership($this->memberships->forUser($row->requestedByUserId));

        try {
            $this->process->reject($actor, $row->applicationId, $row->reason, $row->note);
        } catch (AccessDenied|StateConflict|BusinessRuleViolation|EntityNotFound) {
            return false;
        }

        return true;
    }

    private function tell(PendingRejection $row): void
    {
        $application = $this->applications->find($row->applicationId);
        $jobId = $application?->jobId;

        $this->notifier->send([$row->requestedByUserId], ApplicationNotificationType::REJECTION_DROPPED, [
            'application_id' => $row->applicationId,
            'job_id' => $jobId,
            'job_title' => $jobId === null ? '' : ($this->jobs->titles([$jobId])[$jobId] ?? ''),
        ]);
    }
}
