<?php

namespace Src\Recruitment\Application;

use DateTimeZone;
use Src\Recruitment\Domain\Events\InterviewPanelAnswered;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * Tells the member who scheduled an interview that a panel member cannot attend (`docs/design/phase-8-interviewers.md` section 2): an in-app notice, always written, and an
 * email with the member's note by the preference `team_interview_changes`. A member who declined their own interview tells nobody. In-app and email are separate steps.
 */
final readonly class NotifyOfPanelAnswer
{
    private const string ZONE = 'Asia/Jakarta';

    public function __construct(
        private InterviewRepository $interviews,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
        private CompanyMemberAccounts $accounts,
        private InAppNotifier $notifier,
        private NotificationPreferences $preferences,
        private TeamEmailSender $emails,
        private TeamApplicationPageLink $page,
    ) {}

    public function inApp(InterviewPanelAnswered $event): void
    {
        $interview = $this->interviews->findById($event->interviewId);

        if (! $interview instanceof Interview || $event->createdBy === $event->memberId) {
            return;
        }

        $this->notifier->send([$event->createdBy], ApplicationNotificationType::INTERVIEW_PANEL_DECLINED, [
            'application_id' => $event->applicationId,
            'interview_id' => $event->interviewId,
            'job_id' => $event->jobId,
            'job_title' => $this->jobs->titles([$event->jobId])[$event->jobId] ?? '',
            'member_id' => $event->memberId,
            'note' => $event->note,
            'starts_at' => $interview->startsAt->format(DATE_ATOM),
        ]);
    }

    public function email(InterviewPanelAnswered $event): void
    {
        $interview = $this->interviews->findById($event->interviewId);

        if (! $interview instanceof Interview || $event->createdBy === $event->memberId) {
            return;
        }

        if ($this->preferences->enabledAmong([$event->createdBy], EmailTeamOfEvents::INTERVIEW_PREFERENCE, NotificationChannel::Email) === []) {
            return;
        }

        $accounts = $this->accounts->summaries([$event->createdBy, $event->memberId]);
        $scheduler = $accounts[$event->createdBy] ?? null;

        if ($scheduler === null || $scheduler->status !== 'active') {
            return;
        }

        $language = $scheduler->locale === 'id' ? 'id' : 'en';
        $company = $this->companies->summaries([$event->companyId])[$event->companyId] ?? null;
        $link = $this->page->to($event->applicationId);

        $this->emails->send($scheduler->email, $scheduler->locale, RecruitmentEmailTemplates::TEAM_INTERVIEW_PANEL_DECLINED, [
            'company_id' => (string) $event->companyId,
            'name' => $scheduler->name,
            'job_title' => $this->jobs->titles([$event->jobId])[$event->jobId] ?? '',
            'company_name' => $company === null ? '' : $company->name,
            'member' => isset($accounts[$event->memberId]) ? $accounts[$event->memberId]->name : '',
            'starts_at' => $interview->startsAt->setTimezone(new DateTimeZone(self::ZONE))->format('Y-m-d H:i').' WIB',
            'note' => $event->note === null ? '' : "\n\n".($language === 'id' ? 'Catatan' : 'Note').': '.$event->note,
            'link' => $link === null ? '' : "\n\n".($language === 'id' ? 'Buka lamaran' : 'Open the application').': '.$link,
        ]);
    }
}
