<?php

namespace Src\Assessment\Application\Delivery;

use Src\Assessment\Application\AssessmentEmailTemplates;
use Src\Assessment\Domain\Events\InvitationChange;
use Src\Assessment\Domain\Events\InvitationChanged;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\Assessment\AssessmentInvitationInput;
use Src\Shared\Application\Assessment\AssessmentInvitations;
use Src\Shared\Application\Assessment\InvitedApplication;
use Src\Shared\Application\Clock;

/** The port Recruitment uses to send, keep and cancel the tests of an application. */
final readonly class AssessmentInvitationService implements AssessmentInvitations
{
    public function __construct(
        private InvitationChecks $checks,
        private InvitationPlacer $placer,
        private InvitationRepository $invitations,
        private Clock $clock,
    ) {}

    public function assertCanInvite(int $companyId, int $jobId, AssessmentInvitationInput $input): void
    {
        $this->checks->check($companyId, $jobId, $input);
    }

    public function previewEmail(int $companyId, int $jobId, AssessmentInvitationInput $input, ApplicantContact $contact, string $jobTitle, string $companyName): array
    {
        $checked = $this->checks->check($companyId, $jobId, $input);

        return [
            'key' => AssessmentEmailTemplates::TEST_INVITED,
            'variables' => InvitationEmailVariables::of($checked->test->title(), $checked->dueAt, $checked->mode, $checked->location, $checked->note, $contact, $jobTitle, $companyName, $companyId),
        ];
    }

    public function invite(InvitedApplication $application, int $invitedByUserId, AssessmentInvitationInput $input): array
    {
        return [$this->placer->place($application, $invitedByUserId, $input)->event];
    }

    public function hasActive(int $applicationId): bool
    {
        return $this->invitations->active($applicationId) instanceof Invitation;
    }

    public function applicationIdsWithPendingTest(int $companyId, ?array $jobIds): array
    {
        return $this->invitations->activeApplicationIds($companyId, $jobIds);
    }

    public function cancelActive(InvitedApplication $application): array
    {
        $active = $this->invitations->active($application->applicationId, forUpdate: true);

        if (! $active instanceof Invitation) {
            return [];
        }

        $cancelled = $active->cancel($this->clock->now());
        $this->invitations->update($cancelled);

        return [InvitationChanged::of(InvitationChange::Cancelled, $cancelled, $this->clock->now())];
    }
}
