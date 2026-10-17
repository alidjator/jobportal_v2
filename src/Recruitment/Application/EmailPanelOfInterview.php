<?php

namespace Src\Recruitment\Application;

use DateTimeZone;
use Src\Recruitment\Domain\Events\InterviewChange;
use Src\Recruitment\Domain\Events\InterviewChanged;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewFormat;
use Src\Recruitment\Domain\InterviewPanel;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * The invitation of the interview's panel (`docs/design/phase-8-interviewers.md` section 2): an email with the calendar file (`METHOD:REQUEST`, the attendee is the member)
 * to the main interviewer and the extra members when the interview is scheduled or gets a new time, and to a member added to the panel; the cancellation
 * (`METHOD:CANCEL`) to a member who left the panel, to the panel of an interview the team cancelled and to the members a new time no longer includes. All follow the
 * preference `team_interview_changes`; the in-app notice is written elsewhere. A suspended member or an inactive account gets none.
 */
final readonly class EmailPanelOfInterview
{
    private const string ZONE = 'Asia/Jakarta';

    private const array FORMATS = [
        'online' => ['id' => 'Online', 'en' => 'Online'],
        'onsite' => ['id' => 'Tatap muka', 'en' => 'On site'],
        'video' => ['id' => 'Video di platform', 'en' => 'Video on the platform'],
        'voice' => ['id' => 'Telepon dari platform', 'en' => 'Phone call from the platform'],
    ];

    public function __construct(
        private InterviewRepository $interviews,
        private InterviewPanel $panel,
        private JobDirectory $jobs,
        private CompanyDirectory $companies,
        private CompanyMemberAccounts $accounts,
        private NotificationPreferences $preferences,
        private ApplicantContacts $contacts,
        private InterviewCalendar $calendar,
        private TeamEmailSender $emails,
        private TeamApplicationPageLink $page,
        private Clock $clock,
    ) {}

    public function handle(InterviewChanged $event): void
    {
        $interview = match ($event->change) {
            InterviewChange::Scheduled, InterviewChange::Rescheduled, InterviewChange::PanelChanged, InterviewChange::Cancelled => $this->interviews->findById($event->interviewId),
            default => null,
        };

        if (! $interview instanceof Interview) {
            return;
        }

        $members = array_values(array_unique([$interview->interviewerId, ...($this->panel->forInterviews([$event->interviewId])[$event->interviewId] ?? [])]));

        match ($event->change) {
            InterviewChange::Scheduled, InterviewChange::Rescheduled => $this->send($event, $interview, $members, $event->removedUserIds),
            InterviewChange::PanelChanged => $this->send($event, $interview, $event->addedUserIds, $event->removedUserIds),
            default => $this->send($event, $interview, [], $members),
        };
    }

    /**
     * @param  list<int>  $invite
     * @param  list<int>  $cancel
     */
    private function send(InterviewChanged $event, Interview $interview, array $invite, array $cancel): void
    {
        $recipients = $this->preferences->enabledAmong(array_values(array_unique([...$invite, ...$cancel])), EmailTeamOfEvents::INTERVIEW_PREFERENCE, NotificationChannel::Email);

        if ($recipients === []) {
            return;
        }

        $jobTitle = $this->jobs->titles([$event->jobId])[$event->jobId] ?? '';
        $company = $this->companies->summaries([$event->companyId])[$event->companyId] ?? null;
        $companyName = $company === null ? '' : $company->name;
        $candidate = $this->contacts->contact($event->applicantUserId)?->name ?? '';
        $link = $this->page->to($event->applicationId);
        $now = $this->clock->now();
        $startsAt = $interview->startsAt->setTimezone(new DateTimeZone(self::ZONE))->format('Y-m-d H:i').' WIB';

        foreach ($this->accounts->summaries($recipients) as $userId => $account) {
            if ($account->status !== 'active') {
                continue;
            }

            $language = $account->locale === 'id' ? 'id' : 'en';
            $invited = in_array($userId, $invite, true);
            $method = $invited ? InterviewCalendar::REQUEST : InterviewCalendar::CANCEL;

            if (! $invited && ! in_array($userId, $cancel, true)) {
                continue;
            }

            $this->emails->send($account->email, $account->locale, $invited ? RecruitmentEmailTemplates::TEAM_INTERVIEW_INVITATION : RecruitmentEmailTemplates::TEAM_INTERVIEW_REMOVED, [
                'company_id' => (string) $event->companyId,
                'name' => $account->name,
                'job_title' => $jobTitle,
                'company_name' => $companyName,
                'candidate' => $candidate,
                'starts_at' => $startsAt,
                'format' => self::FORMATS[$interview->format->value][$language],
                'where' => $this->where($interview, $language),
                'kit' => $interview->kitName === null ? '' : "\n\n".($language === 'id' ? 'Kit pertanyaan' : 'Question kit').': '.$interview->kitName,
                'link' => $link === null ? '' : "\n\n".($language === 'id' ? 'Buka lamaran' : 'Open the application').': '.$link,
            ], $this->calendar->write($interview, $method, $jobTitle, $companyName, ['name' => $account->name, 'email' => $account->email], $now), $method);
        }
    }

    private function where(Interview $interview, string $language): string
    {
        return match ($interview->format) {
            InterviewFormat::Online => $interview->meetingUrl ?? '',
            InterviewFormat::Onsite => $interview->location ?? '',
            InterviewFormat::Video => $language === 'id' ? 'ruang video di platform' : 'the video room on the platform',
            InterviewFormat::Voice => $language === 'id' ? 'panggilan telepon dari platform' : 'a phone call from the platform',
        };
    }
}
