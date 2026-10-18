<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Events\ApplicationStageChanged;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * Tells the applicant that a recruiter moved their application into `interview`, `offer`, `hired` or `rejected`: every such move notifies, including a
 * move back into one of them, except a move between two stages of the same type. The in-app notification is always written; the email only when the
 * applicant's preference `application_update` allows it. Nothing is sent to an account that no longer exists or was anonymised. The in-app notification
 * and the email are separate steps, so a retry of one never repeats the other. Neither carries the rejection reason, the note or the recruiter's name.
 * The hired email is the exception: it carries the join date and the note for the candidate of the hire, under the company's logo and the anti-scam line.
 */
final readonly class NotifyApplicantOfStageChange
{
    public function __construct(
        private ApplicantContacts $contacts,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
        private InAppNotifier $notifier,
        private NotificationPreferences $preferences,
        private StageChangeEmailSender $emails,
        private ApplicantEmailSender $framedEmails,
    ) {}

    public function inApp(ApplicationStageChanged $event): void
    {
        $type = $this->announcedType($event);

        if ($type === null || $this->contacts->contact($event->applicantUserId) === null) {
            return;
        }

        [$jobTitle, $companyName] = $this->names($event);

        $this->notifier->send([$event->applicantUserId], $type, [
            'application_id' => $event->applicationId,
            'job_id' => $event->jobId,
            'job_title' => $jobTitle,
            'company_name' => $companyName,
        ]);
    }

    public function email(ApplicationStageChanged $event): void
    {
        $contact = $this->announcedType($event) === null ? null : $this->contacts->contact($event->applicantUserId);

        if (! $contact instanceof ApplicantContact || ! $this->preferences->isEnabled($event->applicantUserId, ApplicationNotificationType::UPDATE_PREFERENCE, NotificationChannel::Email)) {
            return;
        }

        [$jobTitle, $companyName] = $this->names($event);

        if ($event->toType === StageType::Hired) {
            $this->framedEmails->send($contact, RecruitmentEmailTemplates::HIRED, [
                'company_id' => (string) $event->companyId,
                'job_title' => $jobTitle,
                'company_name' => $companyName,
                ...HireEmailVariables::for($event->hire, $contact->locale),
            ]);

            return;
        }

        $this->emails->send($contact, $event->toType, $jobTitle, $companyName);
    }

    private function announcedType(ApplicationStageChanged $event): ?string
    {
        return $event->fromType === $event->toType || $event->noticeCovered ? null : ApplicationNotificationType::forStage($event->toType);
    }

    /** @return array{string, string} */
    private function names(ApplicationStageChanged $event): array
    {
        $companies = $this->companies->summaries([$event->companyId]);

        return [
            $this->jobs->titles([$event->jobId])[$event->jobId] ?? '',
            isset($companies[$event->companyId]) ? $companies[$event->companyId]->name : '',
        ];
    }
}
