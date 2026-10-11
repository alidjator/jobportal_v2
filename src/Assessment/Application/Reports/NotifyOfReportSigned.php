<?php

namespace Src\Assessment\Application\Reports;

use Src\Assessment\Application\AssessmentEmailTemplates;
use Src\Assessment\Application\Delivery\AssessmentNotificationType;
use Src\Assessment\Application\Delivery\TestEmailSender;
use Src\Assessment\Domain\Events\ReportSigned;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\InvitationRepository;
use Src\Assessment\Domain\TestRepository;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * Tells the member who sent the test, in the app only, that its report was signed, and tells the candidate that the explanation of the result can be read.
 * The in-app data holds ids, titles and the report number, never a score. The candidate's in-app notification is always written; the email follows the
 * preference `application_update`. The three are separate steps, so a retry of one never repeats another.
 */
final readonly class NotifyOfReportSigned
{
    public function __construct(
        private InvitationRepository $invitations,
        private TestRepository $tests,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
        private ApplicantContacts $contacts,
        private InAppNotifier $notifier,
        private NotificationPreferences $preferences,
        private TestEmailSender $emails,
    ) {}

    public function inApp(ReportSigned $event): void
    {
        $invitation = $this->invitations->find($event->invitationId);

        if (! $invitation instanceof Invitation) {
            return;
        }

        $this->notifier->send([$invitation->invitedByUserId], AssessmentNotificationType::REPORT_SIGNED, [
            'application_id' => $event->applicationId,
            'invitation_id' => $event->invitationId,
            'job_id' => $event->jobId,
            'job_title' => $this->jobTitle($event),
            'test_title' => $this->tests->find($invitation->testId)?->title() ?? '',
            'report_number' => $event->reportNumber,
        ]);
    }

    public function candidateInApp(ReportSigned $event): void
    {
        $invitation = $this->invitations->find($event->invitationId);

        if (! $invitation instanceof Invitation || $this->contacts->contact($invitation->applicantUserId) === null) {
            return;
        }

        $this->notifier->send([$invitation->applicantUserId], AssessmentNotificationType::EXPLANATION_READY, [
            'application_id' => $event->applicationId,
            'invitation_id' => $event->invitationId,
            'job_id' => $event->jobId,
            'job_title' => $this->jobTitle($event),
            'company_name' => $this->companyName($event),
            'test_title' => $this->tests->find($invitation->testId)?->title() ?? '',
        ]);
    }

    public function candidateEmail(ReportSigned $event): void
    {
        $invitation = $this->invitations->find($event->invitationId);
        $contact = $invitation instanceof Invitation ? $this->contacts->contact($invitation->applicantUserId) : null;

        if (! $invitation instanceof Invitation || ! $contact instanceof ApplicantContact) {
            return;
        }

        if (! $this->preferences->isEnabled($invitation->applicantUserId, AssessmentNotificationType::UPDATE_PREFERENCE, NotificationChannel::Email)) {
            return;
        }

        $this->emails->send($contact, AssessmentEmailTemplates::EXPLANATION_READY, [
            'job_title' => $this->jobTitle($event),
            'company_name' => $this->companyName($event),
            'test_title' => $this->tests->find($invitation->testId)?->title() ?? '',
        ]);
    }

    private function jobTitle(ReportSigned $event): string
    {
        return $this->jobs->titles([$event->jobId])[$event->jobId] ?? '';
    }

    private function companyName(ReportSigned $event): string
    {
        $summary = $this->companies->summaries([$event->companyId])[$event->companyId] ?? null;

        return $summary === null ? '' : $summary->name;
    }
}
