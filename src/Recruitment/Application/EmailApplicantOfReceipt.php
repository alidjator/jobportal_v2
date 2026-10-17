<?php

namespace Src\Recruitment\Application;

use DateTimeZone;
use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Events\ApplicationSubmitted;
use Src\Shared\Application\Applicant\ApplicantContact;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyMessageTexts;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * Confirms to the job seeker by email that the company received their application (`phase-8-recruiter-workday.md` section 5): the job, the company, the date,
 * the company's own message when it set one, and the link to the application page when that page is configured. The preference `application_update` decides; there is
 * no in-app notice because the answer to the application already confirms it. It runs after the application was saved, so a failure never undoes it.
 */
final readonly class EmailApplicantOfReceipt
{
    private const string ZONE = 'Asia/Jakarta';

    public function __construct(
        private ApplicationRepository $applications,
        private ApplicantContacts $contacts,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
        private CompanyMessageTexts $texts,
        private NotificationPreferences $preferences,
        private ApplicationPageLink $page,
        private ApplicantEmailSender $emails,
    ) {}

    public function handle(ApplicationSubmitted $event): void
    {
        $application = $this->applications->find($event->applicationId);

        if (! $application instanceof Application) {
            return;
        }

        $contact = $this->contacts->contact($application->userId);

        if (! $contact instanceof ApplicantContact || ! $this->preferences->isEnabled($application->userId, ApplicationNotificationType::UPDATE_PREFERENCE, NotificationChannel::Email)) {
            return;
        }

        $message = $this->texts->applied($event->companyId);
        $link = $this->page->to($event->applicationId);
        $summary = $this->companies->summaries([$event->companyId])[$event->companyId] ?? null;

        $this->emails->send($contact, RecruitmentEmailTemplates::RECEIVED, [
            'company_id' => (string) $event->companyId,
            'job_title' => $this->jobs->titles([$event->jobId])[$event->jobId] ?? '',
            'company_name' => $summary === null ? '' : $summary->name,
            'date' => $event->occurredAt()->setTimezone(new DateTimeZone(self::ZONE))->format('Y-m-d'),
            'message' => $message === null ? '' : "\n\n".($contact->locale === 'id' ? 'Pesan dari perusahaan' : 'A message from the company').': '.$message,
            'link' => $link === null ? '' : "\n\n".($contact->locale === 'id' ? 'Lihat lamaran Anda' : 'See your application').': '.$link,
        ]);
    }
}
