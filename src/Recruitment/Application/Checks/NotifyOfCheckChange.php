<?php

namespace Src\Recruitment\Application\Checks;

use Src\Recruitment\Application\ApplicantEmailSender;
use Src\Recruitment\Application\ApplicationNotificationType;
use Src\Recruitment\Application\RecruitmentEmailTemplates;
use Src\Recruitment\Domain\ApplicationCheck;
use Src\Recruitment\Domain\ApplicationCheckRepository;
use Src\Recruitment\Domain\CheckTypeRepository;
use Src\Recruitment\Domain\Events\CheckChange;
use Src\Recruitment\Domain\Events\CheckChanged;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * Tells the people involved that a pre-employment check changed (`docs/design/phase-8-pre-employment-checks.md` section 3). The job seeker hears what the team did; the member who requested the check hears
 * that the job seeker refused it. The data holds names and times only, never an outcome or a note. In-app notifications are always written; the job seeker's email follows the preference `application_update`
 * and the team gets none. In-app and email are separate steps, so a retry of one never repeats the other.
 */
final readonly class NotifyOfCheckChange
{
    public function __construct(
        private ApplicantContacts $contacts,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
        private CheckTypeRepository $types,
        private InAppNotifier $notifier,
        private ApplicationCheckRepository $checks,
        private NotificationPreferences $preferences,
        private ApplicantEmailSender $emails,
    ) {}

    public function inApp(CheckChanged $event): void
    {
        $data = [
            'application_id' => $event->applicationId,
            'check_id' => $event->checkId,
            'job_id' => $event->jobId,
            'job_title' => $this->jobTitle($event),
            'company_name' => $this->companyName($event),
            'check_name' => $this->checkName($event),
            'scheduled_at' => $event->scheduledAt?->format(DATE_ATOM),
        ];

        if ($event->change === CheckChange::ConsentRefused) {
            $this->notifier->send([$event->requestedBy], ApplicationNotificationType::forCheck($event->change), $data);

            return;
        }

        if ($this->contacts->contact($event->applicantUserId) !== null) {
            $this->notifier->send([$event->applicantUserId], ApplicationNotificationType::forCheck($event->change), $data);
        }
    }

    public function email(CheckChanged $event): void
    {
        $key = RecruitmentEmailTemplates::keyForCheck($event->change);
        $contact = $key === null ? null : $this->contacts->contact($event->applicantUserId);
        $check = $this->checks->find($event->applicationId, $event->checkId);

        if ($key === null || ! $contact instanceof ApplicantContact || ! $check instanceof ApplicationCheck) {
            return;
        }

        if (! $this->preferences->isEnabled($event->applicantUserId, ApplicationNotificationType::UPDATE_PREFERENCE, NotificationChannel::Email)) {
            return;
        }

        $this->emails->send($contact, $key, CheckEmailVariables::of($check, $contact, $this->checkName($event), $this->jobTitle($event), $this->companyName($event), $event->companyId));
    }

    private function jobTitle(CheckChanged $event): string
    {
        return $this->jobs->titles([$event->jobId])[$event->jobId] ?? '';
    }

    private function companyName(CheckChanged $event): string
    {
        return $this->companies->summaries([$event->companyId])[$event->companyId]->name ?? '';
    }

    private function checkName(CheckChanged $event): string
    {
        return $this->types->byIds([$event->checkTypeId])[$event->checkTypeId]->name ?? '';
    }
}
