<?php

namespace Src\Recruitment\Application;

use DateInterval;
use DateTimeImmutable;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewFormat;
use Src\Recruitment\Domain\InterviewPanel;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\TransactionManager;

/**
 * Tells the main interviewer and the panel in-app that a confirmed interview starts within {@see self::MINUTES_AHEAD} minutes, once per interview, with the meeting link of an
 * online one (a video or voice interview is joined from the platform). It runs every few minutes; an interview confirmed later than that is reminded on the next run while
 * it has not started. No email. An application that became final needs no interview any more and gets no reminder.
 */
final readonly class SendStartingSoonReminders
{
    public const int MINUTES_AHEAD = 30;

    public function __construct(
        private InterviewRepository $interviews,
        private ApplicationRepository $applications,
        private InterviewPanel $panel,
        private JobDirectory $jobs,
        private InAppNotifier $notifier,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /** @return int the number of interviews whose panel was told */
    public function execute(): int
    {
        $now = $this->clock->now();
        $count = 0;

        foreach ($this->interviews->dueForStartingReminder($now, $now->add(new DateInterval('PT'.self::MINUTES_AHEAD.'M'))) as $interview) {
            $count += $this->transactions->run(fn (): int => $this->remind($interview, $now) ? 1 : 0);
        }

        return $count;
    }

    private function remind(Interview $interview, DateTimeImmutable $now): bool
    {
        $id = (int) $interview->id;

        if (! $this->interviews->claimStartingReminder($id, $now)) {
            return false;
        }

        $application = $this->applications->find($interview->applicationId);

        if ($application === null || $application->stageType->isTerminal()) {
            return false;
        }

        $this->notifier->send(
            array_values(array_unique([$interview->interviewerId, ...($this->panel->forInterviews([$id])[$id] ?? [])])),
            ApplicationNotificationType::INTERVIEW_STARTING_SOON,
            [
                'application_id' => $interview->applicationId,
                'interview_id' => $id,
                'job_id' => $application->jobId,
                'job_title' => $this->jobs->titles([$application->jobId])[$application->jobId] ?? '',
                'starts_at' => $interview->startsAt->format(DATE_ATOM),
                'format' => $interview->format->value,
                'meeting_url' => $interview->format === InterviewFormat::Online ? $interview->meetingUrl : null,
            ],
        );

        return true;
    }
}
