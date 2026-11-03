<?php

namespace Src\Assessment\Application\Messages;

use Src\Assessment\Application\Delivery\InvitationViews;
use Src\Assessment\Application\Results\VisibleInvitations;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\Report;
use Src\Assessment\Domain\ReportRepository;
use Src\Assessment\Domain\TestKind;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The report behind an invitation for the company members who may read its result; a psychological result is closed to the interview panel. */
final readonly class CompanyReportAccess
{
    public function __construct(
        private VisibleInvitations $visible,
        private InvitationViews $views,
        private AttemptRepository $attempts,
        private ReportRepository $reports,
    ) {}

    /**
     * @return array{0: Invitation, 1: Report}
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound the invitation, or a report it does not have (a skill test, or a battery that was not submitted)
     */
    public function of(Actor $actor, int $invitationId, bool $forUpdate = false): array
    {
        $invitation = $this->visible->findForResults($actor, $invitationId, panelOpens: true);

        if ($this->views->one($invitation)->testKind !== TestKind::Psychological || $this->visible->panelOnly($actor, $invitation)) {
            throw new AccessDenied('Psychological results are closed to the interview panel.', 'company-permission-required', extensions: ['permission' => CompanyPermission::AssessmentsSend->value]);
        }

        $attempt = $this->attempts->findByInvitation($invitationId);
        $report = $attempt === null ? null : $this->reports->findByAttempt((int) $attempt->id, $forUpdate);

        return [$invitation, $report ?? throw EntityNotFound::of('Report', $invitationId)];
    }
}
