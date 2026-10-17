<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Events\ApplicationWithdrawn;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * Tells the job seeker that the team marked their application withdrawn (`docs/design/phase-8-withdrawals.md` section 2); a withdrawal by the job seeker is not
 * announced to them. The in-app notification is always written; the email only when the preference `application_update` allows it, and it carries the
 * `interview.ics` cancel when an interview was active. It never carries the team's note. The two steps are separate, so a retry of one never repeats the other.
 */
final readonly class NotifyApplicantOfWithdrawal
{
    public function __construct(
        private ApplicantContacts $contacts,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
        private InterviewRepository $interviews,
        private InAppNotifier $notifier,
        private NotificationPreferences $preferences,
        private InterviewEmailSender $emails,
        private InterviewCalendar $calendar,
        private Clock $clock,
    ) {}

    public function inApp(ApplicationWithdrawn $event): void
    {
        if (! $event->byTeam() || $this->contacts->contact($event->applicantUserId) === null) {
            return;
        }

        $this->notifier->send([$event->applicantUserId], ApplicationNotificationType::WITHDRAWN_BY_COMPANY, [
            'application_id' => $event->applicationId,
            'job_id' => $event->jobId,
            'job_title' => $this->jobTitle($event),
            'company_name' => $this->companyName($event),
            'reason' => $event->reason?->value,
        ]);
    }

    public function email(ApplicationWithdrawn $event): void
    {
        $contact = $event->byTeam() ? $this->contacts->contact($event->applicantUserId) : null;

        if (! $contact instanceof ApplicantContact || ! $this->preferences->isEnabled($event->applicantUserId, ApplicationNotificationType::UPDATE_PREFERENCE, NotificationChannel::Email)) {
            return;
        }

        $jobTitle = $this->jobTitle($event);
        $companyName = $this->companyName($event);
        $interview = $event->cancelledInterviewId === null ? null : $this->interviews->findById($event->cancelledInterviewId);
        $calendar = $interview instanceof Interview
            ? $this->calendar->write($interview, InterviewCalendar::CANCEL, $jobTitle, $companyName, ['name' => $contact->name, 'email' => $contact->email], $this->clock->now())
            : null;

        $this->emails->send($contact, RecruitmentEmailTemplates::APPLICATION_WITHDRAWN_BY_COMPANY, [
            'company_id' => (string) $event->companyId,
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'reason' => $event->reason === null ? '' : "\n\n".($contact->locale === 'id' ? 'Alasan' : 'Reason').': '.$event->reason->label($contact->locale),
        ], $calendar, InterviewCalendar::CANCEL);
    }

    private function jobTitle(ApplicationWithdrawn $event): string
    {
        return $this->jobs->titles([$event->jobId])[$event->jobId] ?? '';
    }

    private function companyName(ApplicationWithdrawn $event): string
    {
        $summary = $this->companies->summaries([$event->companyId])[$event->companyId] ?? null;

        return $summary === null ? '' : $summary->name;
    }
}
