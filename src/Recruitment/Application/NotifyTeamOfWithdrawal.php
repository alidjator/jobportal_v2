<?php

namespace Src\Recruitment\Application;

use DateTimeZone;
use Src\Recruitment\Domain\Events\ApplicationWithdrawn;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewPanel;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Shared\Application\Accounts\CompanyMemberAccounts;
use Src\Shared\Application\Applicant\ApplicantContacts;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyTeam;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\Notifications\NotificationChannel;
use Src\Shared\Application\Notifications\NotificationPreferences;

/**
 * Tells the company's team that an application was withdrawn (`docs/design/phase-8-withdrawals.md` section 3). The job's notification recipients get an in-app
 * notice with ids and codes only (never a name), except the members the event leaves out and the hiring managers who do not see the application yet. When
 * the withdrawal cancelled an interview, its main interviewer and panel (the member who marked it left out) get an in-app notice and an email with the
 * `interview.ics` cancel, so the event they added to their calendar disappears. The email follows the member's preference `team_interview_changes`. Every step stands alone, so a retry of one never repeats another.
 */
final readonly class NotifyTeamOfWithdrawal
{
    private const string ZONE = 'Asia/Jakarta';

    public function __construct(
        private JobDirectory $jobs,
        private CompanyTeam $team,
        private CompanyDirectory $companies,
        private CompanyMemberAccounts $members,
        private ApplicantContacts $contacts,
        private InterviewRepository $interviews,
        private InterviewPanel $panel,
        private InAppNotifier $notifier,
        private NotificationPreferences $preferences,
        private TeamEmailSender $emails,
        private InterviewCalendar $calendar,
        private TeamApplicationPageLink $page,
        private Clock $clock,
    ) {}

    public function assignees(ApplicationWithdrawn $event): void
    {
        $bound = array_diff($this->jobs->hiringManagerUserIds($event->jobId), $this->team->viewAllUserIds($event->companyId));
        $recipients = array_values(array_diff($this->jobs->notificationRecipients($event->jobId), $bound, $event->excludedUserIds));

        $this->notifier->send($recipients, ApplicationNotificationType::WITHDRAWN, [
            'application_id' => $event->applicationId,
            'job_id' => $event->jobId,
            'reason' => $event->reason?->value,
            'by' => $event->byTeam() ? 'recruiter' : 'applicant',
        ]);
    }

    public function interviewersInApp(ApplicationWithdrawn $event): void
    {
        $interview = $this->cancelledInterview($event);

        if (! $interview instanceof Interview) {
            return;
        }

        $this->notifier->send($this->interviewers($event, $interview), ApplicationNotificationType::INTERVIEW_CANCELLED_FOR_TEAM, [
            'interview_id' => (int) $interview->id,
            'application_id' => $event->applicationId,
            'starts_at' => $interview->startsAt->format(DATE_ATOM),
        ]);
    }

    public function interviewersEmail(ApplicationWithdrawn $event): void
    {
        $interview = $this->cancelledInterview($event);

        if (! $interview instanceof Interview) {
            return;
        }

        $recipients = $this->preferences->enabledAmong($this->interviewers($event, $interview), EmailTeamOfEvents::INTERVIEW_PREFERENCE, NotificationChannel::Email);
        $accounts = $this->members->summaries($recipients);
        $jobTitle = $this->jobs->titles([$event->jobId])[$event->jobId] ?? '';
        $company = $this->companies->summaries([$event->companyId])[$event->companyId] ?? null;
        $companyName = $company === null ? '' : $company->name;
        $candidate = $this->contacts->contact($event->applicantUserId)?->name;
        $link = $this->page->to($event->applicationId);
        $calendar = $this->calendar->forTeam()->write($interview, InterviewCalendar::CANCEL, $jobTitle, $companyName, null, $this->clock->now());
        $startsAt = $interview->startsAt->setTimezone(new DateTimeZone(self::ZONE))->format('Y-m-d H:i').' WIB';

        foreach ($recipients as $userId) {
            $account = $accounts[$userId] ?? null;

            if ($account === null || $account->status !== 'active') {
                continue;
            }

            $this->emails->send($account->email, $account->locale, RecruitmentEmailTemplates::INTERVIEW_CANCELLED_TEAM, [
                'company_id' => (string) $event->companyId,
                'name' => $account->name,
                'job_title' => $jobTitle,
                'company_name' => $companyName,
                'candidate' => $candidate ?? ($account->locale === 'id' ? 'kandidat' : 'the candidate'),
                'starts_at' => $startsAt,
                'link' => $link === null ? '' : "\n\n".($account->locale === 'id' ? 'Buka lamaran' : 'Open the application').': '.$link,
            ], $calendar, InterviewCalendar::CANCEL);
        }
    }

    private function cancelledInterview(ApplicationWithdrawn $event): ?Interview
    {
        return $event->cancelledInterviewId === null ? null : $this->interviews->findById($event->cancelledInterviewId);
    }

    /**
     * The main interviewer and the extra panel members, without the member who marked the withdrawal.
     *
     * @return list<int>
     */
    private function interviewers(ApplicationWithdrawn $event, Interview $interview): array
    {
        $panel = $this->panel->forInterviews([(int) $interview->id])[(int) $interview->id] ?? [];

        return array_values(array_diff(array_unique([$interview->interviewerId, ...$panel]), $event->actorId === null ? [] : [$event->actorId]));
    }
}
