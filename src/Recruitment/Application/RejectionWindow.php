<?php

namespace Src\Recruitment\Application;

use Ramsey\Uuid\Uuid;
use Src\Recruitment\Domain\PendingRejection;
use Src\Recruitment\Domain\PendingRejectionRepository;
use Src\Recruitment\Domain\RejectionReason;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Settings\Settings;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Where every rejection of the team goes through (the single reject, the bulk reject, "mark as filled" and "reject the remaining"): the company's undo window decides whether the rejection takes
 * effect now or waits (`docs/design/phase-8-rejections.md` section 2). With a window of 0 minutes it is the immediate rejection of {@see ProcessApplication::reject()}; otherwise the request is checked as a
 * rejection would be, stored as a {@see PendingRejection} and applied by `recruitment:commit-rejections` once the window has passed, unless the team undoes it first.
 */
final readonly class RejectionWindow
{
    public function __construct(
        private ProcessApplication $process,
        private RecruitmentAccess $access,
        private PendingRejectionRepository $pending,
        private Settings $settings,
        private Clock $clock,
    ) {}

    /**
     * @return PendingRejection|null the waiting rejection, or null when the application was rejected at once
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound
     * @throws StateConflict `application-final`, `job-taken-down`, `offer-pending` (with `$sparePendingOffer`), `applications-unavailable` or `rejection-pending`
     * @throws BusinessRuleViolation `invalid-rejection`
     */
    public function request(Actor $actor, int $applicationId, RejectionReason $reason, ?string $note, bool $sparePendingOffer = false, ?string $batchId = null): ?PendingRejection
    {
        $member = $this->access->viewer($actor, CompanyPermission::ApplicationsManage);
        $minutes = $this->settings->integer(RecruitmentSettings::REJECTION_UNDO_MINUTES, $member->companyId);

        if ($minutes === 0) {
            $this->process->reject($actor, $applicationId, $reason, $note, $sparePendingOffer);

            return null;
        }

        $note = $this->process->rejectionNote($reason, $note);
        $application = $this->process->assertRejectable($member, $applicationId, $sparePendingOffer);
        $now = $this->clock->now();
        $pending = new PendingRejection($applicationId, $application->stageId, $reason, $note, $member->userId, $now, $now->modify("+{$minutes} minutes"), $batchId);

        if (! $this->pending->add($pending)) {
            throw new StateConflict('This application already waits to be rejected: undo that first.', 'rejection-pending');
        }

        return $pending;
    }

    /** The id that ties the rejections of one bulk action together, or null when a rejection asked now takes effect at once (the company's undo window is 0). */
    public function newBatchId(Actor $actor): ?string
    {
        $member = $this->access->viewer($actor, CompanyPermission::ApplicationsManage);

        return $this->settings->integer(RecruitmentSettings::REJECTION_UNDO_MINUTES, $member->companyId) > 0 ? Uuid::uuid4()->toString() : null;
    }
}
