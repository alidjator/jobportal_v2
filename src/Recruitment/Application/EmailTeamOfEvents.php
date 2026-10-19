<?php

namespace Src\Recruitment\Application;

use DateTimeZone;
use Src\Recruitment\Domain\Events\ApplicationSubmitted;
use Src\Recruitment\Domain\Events\InterviewChange;
use Src\Recruitment\Domain\Events\InterviewChanged;
use Src\Recruitment\Domain\Events\OfferChange;
use Src\Recruitment\Domain\Events\OfferChanged;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewFormat;
use Src\Recruitment\Domain\InterviewPanel;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * The emails to the company's team about what happened to an application (`docs/design/phase-8-recruiter-workday.md` section 3), each by a preference of the member: the job seeker
 * answered an interview (`team_interview_changes`, to the interviewer and who scheduled it), accepted or declined an offer (`team_offer_answers`, to who sent it) and a new application
 * arrived (`team_new_applications`, off until the member switches it on; to the members of the job, not to hiring managers who do not see it yet). The in-app notices are always written
 * elsewhere. The emails hold the job title and a link, never the candidate's name. A suspended member or an inactive account gets none.
 */
final readonly class EmailTeamOfEvents
{
    public const string INTERVIEW_PREFERENCE = 'team_interview_changes';

    public const string OFFER_PREFERENCE = 'team_offer_answers';

    public const string APPLICATION_PREFERENCE = 'team_new_applications';

    public const string SCORECARD_PREFERENCE = 'team_scorecard_reminders';

    private const string ZONE = 'Asia/Jakarta';

    private const array INTERVIEW_WORDS = [
        'confirmed' => ['id' => 'mengonfirmasi wawancara', 'en' => 'confirmed the interview'],
        'proposed' => ['id' => 'mengusulkan waktu wawancara lain', 'en' => 'proposed another interview time'],
        'reschedule' => ['id' => 'minta pindah jadwal wawancara yang sudah dikonfirmasi', 'en' => 'asked to move the confirmed interview'],
        'declined' => ['id' => 'menolak wawancara', 'en' => 'declined the interview'],
    ];

    private const array OFFER_WORDS = [
        'accepted' => ['id' => 'menerima penawaran', 'en' => 'accepted the offer'],
        'declined' => ['id' => 'menolak penawaran', 'en' => 'declined the offer'],
    ];

    public function __construct(
        private JobDirectory $jobs,
        private CompanyTeam $team,
        private CompanyDirectory $companies,
        private CompanyMemberAccounts $accounts,
        private NotificationPreferences $preferences,
        private InterviewRepository $interviews,
        private InterviewPanel $panel,
        private TeamEmailSender $emails,
        private TeamApplicationPageLink $page,
    ) {}

    public function interviewChanged(InterviewChanged $event): void
    {
        if ($event->change === InterviewChange::Reminder) {
            $this->interviewReminder($event);

            return;
        }

        $words = match ($event->change) {
            InterviewChange::Confirmed => self::INTERVIEW_WORDS['confirmed'],
            InterviewChange::Proposed => self::INTERVIEW_WORDS['proposed'],
            InterviewChange::Declined => self::INTERVIEW_WORDS['declined'],
            default => null,
        };
        $interview = $words === null ? null : $this->interviews->findById($event->interviewId);

        if (! $interview instanceof Interview) {
            return;
        }

        if ($event->change === InterviewChange::Proposed && $interview->status === InterviewStatus::Confirmed) {
            $words = self::INTERVIEW_WORDS['reschedule'];
        }

        $this->send(
            array_values(array_unique([$event->interviewerId, $event->createdBy])), self::INTERVIEW_PREFERENCE, RecruitmentEmailTemplates::TEAM_INTERVIEW_CHANGED,
            $event->companyId, $event->jobId, $event->applicationId, $words, ['starts_at' => $this->startsAt($interview)],
        );
    }

    public function offerAnswered(OfferChanged $event): void
    {
        $words = match ($event->change) {
            OfferChange::Accepted => self::OFFER_WORDS['accepted'],
            OfferChange::Declined => self::OFFER_WORDS['declined'],
            default => null,
        };

        if ($words === null) {
            return;
        }

        $this->send([$event->createdBy], self::OFFER_PREFERENCE, RecruitmentEmailTemplates::TEAM_OFFER_ANSWERED, $event->companyId, $event->jobId, $event->applicationId, $words);
    }

    public function applicationReceived(ApplicationSubmitted $event): void
    {
        // Like the in-app notice: a hiring manager sees an application only from the stage boundary, so a new one is not announced to them.
        $bound = array_diff($this->jobs->hiringManagerUserIds($event->jobId), $this->team->viewAllUserIds($event->companyId));
        $recipients = array_values(array_diff($this->jobs->notificationRecipients($event->jobId), $bound));

        $this->send($recipients, self::APPLICATION_PREFERENCE, RecruitmentEmailTemplates::TEAM_APPLICATION_RECEIVED, $event->companyId, $event->jobId, $event->applicationId, null);
    }

    /** The day-before reminder to the whole panel: the main interviewer, the extra members and who scheduled it (`team_interview_changes`). */
    private function interviewReminder(InterviewChanged $event): void
    {
        $interview = $this->interviews->findById($event->interviewId);

        if (! $interview instanceof Interview) {
            return;
        }

        $members = array_values(array_unique([$event->interviewerId, $event->createdBy, ...($this->panel->forInterviews([$event->interviewId])[$event->interviewId] ?? [])]));

        $this->send(
            $members, self::INTERVIEW_PREFERENCE, RecruitmentEmailTemplates::TEAM_INTERVIEW_REMINDER,
            $event->companyId, $event->jobId, $event->applicationId, null, ['starts_at' => $this->startsAt($interview)], static fn (string $language): array => ['where' => self::place($interview, $language)],
        );
    }

    /**
     * The email to the panel members whose scorecard is still missing (`team_scorecard_reminders`, on by default).
     *
     * @param  list<int>  $userIds
     */
    public function scorecardMissing(Interview $interview, int $companyId, int $jobId, array $userIds, bool $second): void
    {
        $this->send(
            $userIds, self::SCORECARD_PREFERENCE, RecruitmentEmailTemplates::TEAM_SCORECARD_REMINDER,
            $companyId, $jobId, $interview->applicationId, null, ['starts_at' => $this->startsAt($interview)],
            static fn (string $language): array => ['reminder' => $second ? ($language === 'id' ? ' (pengingat kedua)' : ' (second reminder)') : ''],
        );
    }

    private function startsAt(Interview $interview): string
    {
        return $interview->startsAt->setTimezone(new DateTimeZone(self::ZONE))->format('Y-m-d H:i').' WIB';
    }

    private static function place(Interview $interview, string $language): string
    {
        return match ($interview->format) {
            InterviewFormat::Online => $interview->meetingUrl ?? '',
            InterviewFormat::Onsite => $interview->location ?? '',
            InterviewFormat::Video => $language === 'id' ? 'ruang video di platform' : 'the video room on the platform',
            InterviewFormat::Voice => $language === 'id' ? 'panggilan telepon dari platform' : 'a phone call from the platform',
        };
    }

    /**
     * @param  list<int>  $userIds
     * @param  array{id: string, en: string}|null  $words  what happened, in each language; null when the template has no such variable
     * @param  array<string, string>  $extra
     * @param  (callable(string): array<string, string>)|null  $byLanguage  more variables that depend on the recipient's language
     */
    private function send(array $userIds, string $preference, string $template, int $companyId, int $jobId, int $applicationId, ?array $words, array $extra = [], ?callable $byLanguage = null): void
    {
        $wanted = $this->preferences->enabledAmong($userIds, $preference, NotificationChannel::Email);

        if ($wanted === []) {
            return;
        }

        $jobTitle = $this->jobs->titles([$jobId])[$jobId] ?? '';
        $company = $this->companies->summaries([$companyId])[$companyId] ?? null;
        $link = $this->page->to($applicationId);

        foreach ($this->accounts->summaries($wanted) as $account) {
            if ($account->status !== 'active') {
                continue;
            }

            $language = $account->locale === 'id' ? 'id' : 'en';

            $this->emails->send($account->email, $account->locale, $template, [
                'company_id' => (string) $companyId,
                'name' => $account->name,
                'job_title' => $jobTitle,
                'company_name' => $company === null ? '' : $company->name,
                'link' => $link === null ? '' : "\n\n".($language === 'id' ? 'Buka lamaran' : 'Open the application').': '.$link,
                ...($words === null ? [] : ['what' => $words[$language]]),
                ...$extra,
                ...($byLanguage === null ? [] : $byLanguage($language)),
            ]);
        }
    }
}
