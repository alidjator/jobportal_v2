<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Events\JobFillHandled;
use Src\Recruitment\Domain\RejectionReason;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Job\JobCloser;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * "Mark as filled" (`docs/design/phase-8-hiring.md` section 2): the team closes the job and rejects the applications they chose with the reason `position_filled`.
 * The job is closed first, through the ordinary close (`jobs.publish`): when that is refused (the job is not live) nothing is rejected. Rejecting needs
 * `applications.manage` and goes application by application through {@see ProcessApplication::reject}, so each keeps the rules, history and notifications of a
 * rejection; one that cannot be rejected is skipped with its code (`application-not-found` when it is not of this job, `application-final`, `offer-pending`
 * for a pending offer, which is never rejected this way) without touching the others. Audited with the counts only.
 */
final readonly class FillJob
{
    public function __construct(
        private RecruitmentAccess $access,
        private ProcessApplication $process,
        private ApplicationRepository $applications,
        private JobCloser $closer,
        private JobDirectory $jobs,
        private Settings $settings,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  list<int>  $applicationIds
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound the member may not see the job
     * @throws StateConflict `job-not-active` when asked to close a job that is not live
     * @throws BusinessRuleViolation `too-many-applications` or `invalid-rejection`
     */
    public function execute(Actor $actor, int $jobId, bool $close, array $applicationIds, ?string $note, ?string $ipAddress, ?string $userAgent): FillJobResult
    {
        // A request that only closes the job needs nothing beyond the close itself; one that rejects, or does nothing, needs `applications.manage`.
        $member = $close && $applicationIds === [] ? $this->access->member($actor) : $this->access->viewer($actor, CompanyPermission::ApplicationsManage);
        $this->access->job($member, $jobId);

        $note = $this->process->rejectionNote(RejectionReason::PositionFilled, $note);
        $cap = $this->settings->integer(RecruitmentSettings::MAX_BULK_APPLICATIONS, $member->companyId);

        if (count($applicationIds) > $cap) {
            throw new BusinessRuleViolation('Select at most '.$cap.' applications at a time.', 'too-many-applications', extensions: ['errors' => ['reject_application_ids' => ['Select at most '.$cap.' applications at a time.']]]);
        }

        if ($close) {
            $this->closer->close($actor, $jobId, $ipAddress, $userAgent);
        }

        $items = array_map(fn (int $id): BulkItemResult => $this->reject($actor, $member, $jobId, $id, $note), $applicationIds);
        $rejected = count(array_filter($items, static fn (BulkItemResult $item): bool => $item->wasMoved()));

        $this->events->dispatch([new JobFillHandled($jobId, $member->companyId, $member->userId, $close, $rejected, count($items) - $rejected, $ipAddress, $userAgent, $this->clock->now())]);

        return new FillJobResult($this->jobs->statusOf($jobId) ?? '', $items);
    }

    private function reject(Actor $actor, RecruitmentMember $member, int $jobId, int $applicationId, ?string $note): BulkItemResult
    {
        $application = $this->applications->find($applicationId);

        if (! $application instanceof Application || $application->jobId !== $jobId || $application->companyId !== $member->companyId) {
            return new BulkItemResult($applicationId, 'application-not-found');
        }

        try {
            $this->process->reject($actor, $applicationId, RejectionReason::PositionFilled, $note, sparePendingOffer: true);
        } catch (StateConflict|BusinessRuleViolation $error) {
            return new BulkItemResult($applicationId, $error->errorCode());
        } catch (EntityNotFound) {
            return new BulkItemResult($applicationId, 'application-not-found');
        }

        return new BulkItemResult($applicationId);
    }
}
