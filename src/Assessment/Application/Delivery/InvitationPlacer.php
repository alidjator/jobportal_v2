<?php

namespace Src\Assessment\Application\Delivery;

use Src\Assessment\Domain\Events\InvitationChange;
use Src\Assessment\Domain\Events\InvitationChanged;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\InvitationStatus;
use Src\Shared\Application\Assessment\AssessmentInvitationInput;
use Src\Shared\Application\Assessment\InvitedApplication;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Puts a test on an application: checks the request, cancels the invitation that was active (a new test replaces it, unless the candidate already started
 * it) and saves the new one. It runs inside the caller's transaction, with the application row already locked.
 */
final readonly class InvitationPlacer
{
    public function __construct(
        private InvitationChecks $checks,
        private InvitationRepository $invitations,
        private Clock $clock,
    ) {}

    /**
     * @throws BusinessRuleViolation `invalid-assessment-test`, `invalid-assessment-schedule` or `invalid-assessor`
     * @throws StateConflict `invitation-in-progress`
     */
    public function place(InvitedApplication $application, int $invitedByUserId, AssessmentInvitationInput $input): PlacedInvitation
    {
        $checked = $this->checks->check($application->companyId, $application->jobId, $input);
        $now = $this->clock->now();

        $previous = $this->invitations->active($application->applicationId, forUpdate: true);

        if ($previous instanceof Invitation) {
            if ($previous->status === InvitationStatus::Started) {
                throw new StateConflict('The candidate already started the test. Cancel it before sending another.', 'invitation-in-progress');
            }

            $this->invitations->update($previous->cancel($now));
        }

        $saved = $this->invitations->add(Invitation::send(
            $application->applicationId,
            $application->companyId,
            $application->jobId,
            $application->applicantUserId,
            (int) $checked->test->id,
            $checked->startsAt,
            $checked->dueAt,
            $invitedByUserId,
            $checked->assessorIds,
            $checked->mode,
            $checked->location,
            $checked->note,
            $application->stageId,
        ));

        return new PlacedInvitation($saved, InvitationChanged::of(InvitationChange::Invited, $saved, $now), $previous instanceof Invitation);
    }
}
