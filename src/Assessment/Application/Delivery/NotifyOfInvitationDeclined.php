<?php

namespace Src\Assessment\Application\Delivery;

use Src\Assessment\Domain\Events\InvitationDeclined;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;

/** Tells the team, in the app only, that the candidate declined the test: the member who sent it and the assessors. The data holds ids and titles. */
final readonly class NotifyOfInvitationDeclined
{
    public function __construct(
        private InvitationRepository $invitations,
        private TestRepository $tests,
        private JobDirectory $jobs,
        private InAppNotifier $notifier,
    ) {}

    public function inApp(InvitationDeclined $event): void
    {
        $invitation = $this->invitations->find($event->invitationId);
        $test = $invitation instanceof Invitation ? $this->tests->find($invitation->testId) : null;

        if (! $invitation instanceof Invitation || ! $test instanceof Test) {
            return;
        }

        $this->notifier->send(array_values(array_unique([$invitation->invitedByUserId, ...$invitation->assessorIds])), AssessmentNotificationType::DECLINED, [
            'application_id' => $event->applicationId,
            'invitation_id' => $event->invitationId,
            'job_id' => $event->jobId,
            'job_title' => $this->jobs->titles([$event->jobId])[$event->jobId] ?? '',
            'test_title' => $test->title(),
        ]);
    }
}
