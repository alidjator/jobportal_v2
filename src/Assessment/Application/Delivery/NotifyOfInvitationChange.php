<?php

namespace Src\Assessment\Application\Delivery;

use Src\Assessment\Application\AssessmentEmailTemplates;
use Src\Assessment\Domain\Events\InvitationChanged;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * Tells the candidate that a test was sent, is about to be due, expired or was cancelled. The in-app notification is always written (ids, the test title and
 * the due time only); the email follows the candidate's preference `application_update`. The team gets nothing here: it hears about submissions and signed
 * reports. In-app and email are separate steps, so a retry of one never repeats the other.
 */
final readonly class NotifyOfInvitationChange
{
    public function __construct(
        private ApplicantContacts $contacts,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
        private InvitationRepository $invitations,
        private TestRepository $tests,
        private InAppNotifier $notifier,
        private NotificationPreferences $preferences,
        private TestEmailSender $emails,
    ) {}

    public function inApp(InvitationChanged $event): void
    {
        $invitation = $this->invitations->find($event->invitationId);
        $test = $invitation instanceof Invitation ? $this->tests->find($invitation->testId) : null;

        if (! $invitation instanceof Invitation || ! $test instanceof Test || $this->contacts->contact($event->applicantUserId) === null) {
            return;
        }

        $this->notifier->send([$event->applicantUserId], AssessmentNotificationType::forChange($event->change), [
            'application_id' => $event->applicationId,
            'invitation_id' => $event->invitationId,
            'job_id' => $event->jobId,
            'job_title' => $this->jobTitle($event),
            'company_name' => $this->companyName($event),
            'test_title' => $test->title(),
            'due_at' => $invitation->dueAt->format(DATE_ATOM),
        ]);
    }

    public function email(InvitationChanged $event): void
    {
        $contact = $this->contacts->contact($event->applicantUserId);
        $invitation = $this->invitations->find($event->invitationId);
        $test = $invitation instanceof Invitation ? $this->tests->find($invitation->testId) : null;

        if (! $contact instanceof ApplicantContact || ! $invitation instanceof Invitation || ! $test instanceof Test) {
            return;
        }

        if (! $this->preferences->isEnabled($event->applicantUserId, AssessmentNotificationType::UPDATE_PREFERENCE, NotificationChannel::Email)) {
            return;
        }

        $variables = InvitationEmailVariables::of($test->title(), $invitation->dueAt, $invitation->mode, $invitation->location, $invitation->note, $contact, $this->jobTitle($event), $this->companyName($event), $event->companyId);

        $this->emails->send($contact, AssessmentEmailTemplates::keyForChange($event->change), $variables);
    }

    private function jobTitle(InvitationChanged $event): string
    {
        return $this->jobs->titles([$event->jobId])[$event->jobId] ?? '';
    }

    private function companyName(InvitationChanged $event): string
    {
        $summary = $this->companies->summaries([$event->companyId])[$event->companyId] ?? null;

        return $summary === null ? '' : $summary->name;
    }
}
