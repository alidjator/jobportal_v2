<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Events\RejectRemainingRequested;
use Src\Recruitment\Domain\RejectionReason;
use Src\Recruitment\Domain\Stage;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyMembershipResolver;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * "Reject the remaining" (`docs/design/phase-8-candidate-experience.md` section 1): once a job is closed or has expired, the team rejects the applications still active in the stages they choose
 * (the hired stage is never one of them). The request is checked at once and answers how many applications are in those stages; a queued step then rejects them one by one through
 * {@see RejectionWindow}, so each keeps the rules, history, notices and undo window of a single rejection, and tells the member how many were rejected and how many skipped. No cap: it runs on the queue.
 */
final readonly class RejectRemainingApplications
{
    public function __construct(
        private RecruitmentAccess $access,
        private ProcessApplication $process,
        private ApplicationRepository $applications,
        private RejectionWindow $window,
        private JobDirectory $jobs,
        private CompanyMembershipResolver $memberships,
        private InAppNotifier $notifier,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  list<int>  $stageIds
     * @return int how many applications are in the chosen stages now
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound the member may not see the job
     * @throws StateConflict `job-still-open` while the job is neither closed nor expired
     * @throws BusinessRuleViolation `invalid-stage` or `invalid-rejection`
     */
    public function request(Actor $actor, int $jobId, array $stageIds, RejectionReason $reason, ?string $note): int
    {
        $member = $this->access->viewer($actor, CompanyPermission::ApplicationsManage);
        $this->access->job($member, $jobId);

        if (! in_array($this->jobs->statusOf($jobId), ['closed', 'expired'], true)) {
            throw new StateConflict('Close the job first: only the applications of a closed or expired job can be rejected this way.', 'job-still-open');
        }

        $stageIds = array_values(array_unique($stageIds));

        foreach ($stageIds as $stageId) {
            $stage = $this->process->movableStage($member->companyId, $stageId);

            if ($stage->type === StageType::Hired) {
                $message = 'A hired application is not rejected this way.';

                throw new BusinessRuleViolation($message, 'invalid-stage', extensions: ['errors' => ['stage_ids' => [$message]]]);
            }
        }

        $note = $this->process->rejectionNote($reason, $note);
        $count = count($this->applications->idsInStages($jobId, $stageIds));

        $this->events->dispatch([new RejectRemainingRequested($jobId, $member->companyId, $member->userId, $stageIds, $reason->value, $note, $this->clock->now())]);

        return $count;
    }

    /** The queued step: rejects what is in the stages when it runs, and tells the member the result. */
    public function run(RejectRemainingRequested $event): void
    {
        $actor = Actor::companyMember($event->requestedByUserId)->withMembership($this->memberships->forUser($event->requestedByUserId));
        $reason = RejectionReason::from($event->reason);
        $batchId = $this->window->newBatchId($actor);
        $rejected = 0;
        $skipped = 0;

        foreach ($this->applications->idsInStages($event->jobId, $event->stageIds) as $applicationId) {
            try {
                $this->window->request($actor, $applicationId, $reason, $event->note, sparePendingOffer: true, batchId: $batchId);
                $rejected++;
            } catch (StateConflict|BusinessRuleViolation|EntityNotFound|AccessDenied) {
                $skipped++;
            }
        }

        $this->notifier->send([$event->requestedByUserId], ApplicationNotificationType::REJECT_REMAINING_DONE, [
            'job_id' => $event->jobId,
            'job_title' => $this->jobs->titles([$event->jobId])[$event->jobId] ?? '',
            'rejected' => $rejected,
            'skipped' => $skipped,
            'batch_id' => $batchId,
        ]);
    }
}
