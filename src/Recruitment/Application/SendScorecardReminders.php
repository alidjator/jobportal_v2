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
 * Reminds the panel members who have not sent their scorecard, once per interview, {@see self::HOURS_AFTER} hours after it ended. Only interviews that ended
 * within the last {@see self::LOOKBACK_DAYS} days beyond that are looked at, so the first run after a deployment does not remind about old interviews. In-app
 * only, no audit entry (the scorecard rows are the record). An application that became final needs no scorecard any more and gets no reminder.
 */
final readonly class SendScorecardReminders
{
    public const int HOURS_AFTER = 24;

    public const int LOOKBACK_DAYS = 7;

    public function __construct(
        private InterviewRepository $interviews,
        private ApplicationRepository $applications,
        private InterviewPanel $panel,
        private ScorecardRepository $scorecards,
        private JobDirectory $jobs,
        private InAppNotifier $notifier,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /** @return int the number of interviews whose panel was reminded */
    public function execute(): int
    {
        $now = $this->clock->now();
        $count = 0;

        $due = $this->interviews->dueForScorecardReminder(
            $now->sub(new DateInterval('PT'.self::HOURS_AFTER.'H')),
            $now->sub(new DateInterval('PT'.self::HOURS_AFTER.'H'))->sub(new DateInterval('P'.self::LOOKBACK_DAYS.'D')),
        );

        foreach ($due as $interview) {
            $count += $this->transactions->run(fn (): int => $this->remind($interview, $now) ? 1 : 0);
        }

        return $count;
    }

    private function remind(Interview $interview, DateTimeImmutable $now): bool
    {
        $id = (int) $interview->id;

        if (! $this->interviews->claimScorecardReminder($id, $now)) {
            return false;
        }

        $application = $this->applications->find($interview->applicationId);

        if ($application === null || $application->stageType->isTerminal() || $application->stageType === StageType::Hired) {
            return false;
        }

        $members = [$interview->interviewerId, ...($this->panel->forInterviews([$id])[$id] ?? [])];
        $pending = array_values(array_diff($members, $this->scorecards->sentBy($id)));

        if ($pending === []) {
            return false;
        }

        $this->notifier->send($pending, ApplicationNotificationType::INTERVIEW_SCORECARD_REMINDER, [
            'application_id' => $interview->applicationId,
            'interview_id' => $id,
            'job_id' => $application->jobId,
            'job_title' => $this->jobs->titles([$application->jobId])[$application->jobId] ?? '',
            'ended_at' => $interview->endsAt->format(DATE_ATOM),
        ]);

        return true;
    }
}
