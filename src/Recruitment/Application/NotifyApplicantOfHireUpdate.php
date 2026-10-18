<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Events\ApplicationHireUpdated;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * Tells the job seeker that the team changed the join date or the note of their hired application (`docs/design/phase-8-hiring.md` section 1). The in-app
 * notification is always written; the email only when the preference `application_update` allows it. Nothing is sent to an account that no longer exists or was
 * anonymised. The two steps are separate, so a retry of one never repeats the other.
 */
final readonly class NotifyApplicantOfHireUpdate
{
    public function __construct(
        private ApplicantContacts $contacts,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
        private InAppNotifier $notifier,
        private NotificationPreferences $preferences,
        private ApplicantEmailSender $emails,
    ) {}

    public function inApp(ApplicationHireUpdated $event): void
    {
        if ($this->contacts->contact($event->applicantUserId) === null) {
            return;
        }

        [$jobTitle, $companyName] = $this->names($event);

        $this->notifier->send([$event->applicantUserId], ApplicationNotificationType::HIRE_UPDATED, [
            'application_id' => $event->applicationId,
            'job_id' => $event->jobId,
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            'join_date' => $event->after->joinDate->format('Y-m-d'),
        ]);
    }

    public function email(ApplicationHireUpdated $event): void
    {
        $contact = $this->contacts->contact($event->applicantUserId);

        if (! $contact instanceof ApplicantContact || ! $this->preferences->isEnabled($event->applicantUserId, ApplicationNotificationType::UPDATE_PREFERENCE, NotificationChannel::Email)) {
            return;
        }

        [$jobTitle, $companyName] = $this->names($event);

        $this->emails->send($contact, RecruitmentEmailTemplates::HIRE_UPDATED, [
            'company_id' => (string) $event->companyId,
            'job_title' => $jobTitle,
            'company_name' => $companyName,
            ...HireEmailVariables::for($event->after, $contact->locale),
        ]);
    }

    /** @return array{string, string} */
    private function names(ApplicationHireUpdated $event): array
    {
        $companies = $this->companies->summaries([$event->companyId]);

        return [
            $this->jobs->titles([$event->jobId])[$event->jobId] ?? '',
            isset($companies[$event->companyId]) ? $companies[$event->companyId]->name : '',
        ];
    }
}
