<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\PendingRejectionRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The team takes back a rejection that still waits its undo window (`docs/design/phase-8-rejections.md` section 2), for one application or for every application of one bulk action. Whoever
 * may reject the application may undo it. Nothing is left behind: no history entry and nothing for the job seeker, who was never told.
 */
final readonly class UndoRejection
{
    public function __construct(
        private RecruitmentAccess $access,
        private CompanyApplicationAccess $applicationAccess,
        private ApplicationRepository $applications,
        private PendingRejectionRepository $pending,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound the application is not one the member may see, or it waits for no rejection
     * @throws StateConflict `rejection-committed` when the window has passed and the rejection is about to take effect
     */
    public function execute(Actor $actor, int $applicationId): void
    {
        $member = $this->access->viewer($actor, CompanyPermission::ApplicationsManage);
        $application = $this->applications->find($applicationId) ?? throw EntityNotFound::of('Application', $applicationId);
        $this->applicationAccess->assertCanSee($member, $applicationId, $application->companyId, $application->jobId);

        if ($this->pending->undo($applicationId, $this->clock->now())) {
            return;
        }

        if ($this->pending->exists($applicationId)) {
            throw new StateConflict('The rejection is already taking effect and cannot be undone.', 'rejection-committed');
        }

        throw EntityNotFound::of('Pending rejection', $applicationId);
    }

    /**
     * @return int how many rejections of the batch were undone
     *
     * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
     * @throws EntityNotFound the batch has no rejection of the company left to undo
     */
    public function executeBatch(Actor $actor, string $batchId): int
    {
        $member = $this->access->viewer($actor, CompanyPermission::ApplicationsManage);
        $undone = $this->pending->undoBatch($batchId, $member->companyId, $this->clock->now());

        if ($undone === []) {
            throw EntityNotFound::of('Rejection batch');
        }

        return count($undone);
    }
}
