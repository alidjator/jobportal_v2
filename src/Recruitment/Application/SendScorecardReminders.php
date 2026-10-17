<?php

namespace Src\Recruitment\Application;

use DateInterval;
use DateTimeImmutable;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewPanel;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\ScorecardRepository;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\TransactionManager;

/**
 * Follows up on the scorecards of the panel members, once per interview and step. {@see self::HOURS_AFTER} hours after the interview ended the members without a sent
 * scorecard get an in-app notice and an email (`team_scorecard_reminders`); {@see self::ESCALATION_HOURS_AFTER} hours after it they get both again, and the member who
 * scheduled the interview and the main interviewer are told in-app how many scorecards are still missing. Only interviews that ended within the last
 * {@see self::LOOKBACK_DAYS} days beyond a step's hour are looked at, so the first run after a deployment does not remind about old interviews. The in-app notices are always
 * written, no audit entry (the scorecard rows are the record). An application that became final needs no scorecard any more and gets no reminder.
 */
final readonly class SendScorecardReminders
{
    public const int HOURS_AFTER = 24;

    public const int ESCALATION_HOURS_AFTER = 72;

    public const int LOOKBACK_DAYS = 7;

    public function __construct(
        private InterviewRepository $interviews,
        private ApplicationRepository $applications,
        private InterviewPanel $panel,
        private ScorecardRepository $scorecards,
        private JobDirectory $jobs,
        private InAppNotifier $notifier,
        private EmailTeamOfEvents $emails,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /** @return int the number of interviews whose panel was reminded */
    public function execute(): int
    {
        $now = $this->clock->now();
        $count = 0;

        foreach ($this->interviews->dueForScorecardReminder($this->before($now, self::HOURS_AFTER), $this->before($now, self::HOURS_AFTER + self::LOOKBACK_DAYS * 24)) as $interview) {
            $reminded = $this->transactions->run(fn (): ?array => $this->remind($interview, $now, second: false));

            if ($reminded !== null) {
                $this->emails->scorecardMissing($interview, $reminded['company_id'], $reminded['job_id'], $reminded['pending'], second: false);
                $count++;
            }
        }

        return $count;
    }

    /** @return int the number of interviews followed up a second time */
    public function escalate(): int
    {
        $now = $this->clock->now();
        $count = 0;

        foreach ($this->interviews->dueForScorecardEscalation($this->before($now, self::ESCALATION_HOURS_AFTER), $this->before($now, self::ESCALATION_HOURS_AFTER + self::LOOKBACK_DAYS * 24)) as $interview) {
            $reminded = $this->transactions->run(fn (): ?array => $this->remind($interview, $now, second: true));

            if ($reminded !== null) {
                $this->emails->scorecardMissing($interview, $reminded['company_id'], $reminded['job_id'], $reminded['pending'], second: true);
                $count++;
            }
        }

        return $count;
    }

    private function before(DateTimeImmutable $now, int $hours): DateTimeImmutable
    {
        return $now->sub(new DateInterval('PT'.$hours.'H'));
    }

    /** @return array{company_id: int, job_id: int, pending: list<int>}|null null when nothing was sent (claimed by another run, final application or nobody missing) */
    private function remind(Interview $interview, DateTimeImmutable $now, bool $second): ?array
    {
        $id = (int) $interview->id;

        if (! ($second ? $this->interviews->claimScorecardEscalation($id, $now) : $this->interviews->claimScorecardReminder($id, $now))) {
            return null;
        }

        $application = $this->applications->find($interview->applicationId);

        if ($application === null || $application->stageType->isTerminal() || $application->stageType === StageType::Hired) {
            return null;
        }

        $members = [$interview->interviewerId, ...($this->panel->forInterviews([$id])[$id] ?? [])];
        $pending = array_values(array_diff($members, $this->scorecards->sentBy($id)));

        if ($pending === []) {
            return null;
        }

        $data = [
            'application_id' => $interview->applicationId,
            'interview_id' => $id,
            'job_id' => $application->jobId,
            'job_title' => $this->jobs->titles([$application->jobId])[$application->jobId] ?? '',
            'ended_at' => $interview->endsAt->format(DATE_ATOM),
        ];

        $this->notifier->send($pending, ApplicationNotificationType::INTERVIEW_SCORECARD_REMINDER, [...$data, 'second' => $second]);

        if ($second) {
            $this->notifier->send(array_values(array_unique([$interview->createdBy, $interview->interviewerId])), ApplicationNotificationType::INTERVIEW_SCORECARDS_MISSING, [...$data, 'missing' => count($pending)]);
        }

        return ['company_id' => $application->companyId, 'job_id' => $application->jobId, 'pending' => $pending];
    }
}
