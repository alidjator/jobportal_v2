<?php

namespace Src\Recruitment\Application;

use DateInterval;
use DateTimeImmutable;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Events\InterviewChange;
use Src\Recruitment\Domain\Events\InterviewChanged;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewFormat;
use Src\Recruitment\Domain\InterviewPanel;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\InterviewRsvps;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Recruitment\Domain\Rsvp;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Application\TransactionManager;

/**
 * Tells the main interviewer and the panel in-app, but not the members who answered that they cannot attend, that a confirmed interview starts within {@see self::MINUTES_AHEAD} minutes, once per interview, with the meeting link of an
 * online one (a video or voice interview is joined from the platform). It runs every few minutes; an interview confirmed later than that is reminded on the next run while
 * it has not started. No email. An application that became final needs no interview any more and gets no reminder.
 *
 * The job seeker gets their own reminder {@see self::CANDIDATE_MINUTES_AHEAD} minutes ahead, in-app and by email (`docs/design/phase-8-interview-reschedule.md` section 6), and
 * the same run lets the proposals lapse that the team left unanswered until the current start.
 */
final readonly class SendStartingSoonReminders
{
    public const int MINUTES_AHEAD = 30;

    public const int CANDIDATE_MINUTES_AHEAD = 120;

    public function __construct(
        private InterviewRepository $interviews,
        private ApplicationRepository $applications,
        private InterviewPanel $panel,
        private InterviewRsvps $rsvps,
        private JobDirectory $jobs,
        private InAppNotifier $notifier,
        private TransactionManager $transactions,
        private EventDispatcher $events,
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

    /** @return int the number of job seekers reminded */
    public function remindCandidates(): int
    {
        $now = $this->clock->now();
        $count = 0;

        foreach ($this->interviews->dueForCandidateSoonReminder($now, $now->add(new DateInterval('PT'.self::CANDIDATE_MINUTES_AHEAD.'M'))) as $due) {
            $event = $this->transactions->run(function () use ($due, $now): ?InterviewChanged {
                $interview = $this->interviews->active($due->applicationId, forUpdate: true);

                // Changed since it was listed: not confirmed any more, or already reminded.
                if (! $interview instanceof Interview || $interview->id !== $due->id || $interview->status !== InterviewStatus::Confirmed || $interview->candidateSoonRemindedAt !== null) {
                    return null;
                }

                $application = $this->applications->find($interview->applicationId);

                if ($application === null || $application->stageType->isTerminal()) {
                    return null;
                }

                $this->interviews->update($interview->remindedSoon($now));

                return InterviewChanged::of(InterviewChange::StartingSoon, $application, $interview, $now);
            });

            if ($event instanceof InterviewChanged) {
                $this->events->dispatch([$event]);
                $count++;
            }
        }

        return $count;
    }

    /** @return int the number of proposals that lapsed */
    public function lapseProposals(): int
    {
        return $this->interviews->lapseProposals($this->clock->now());
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

        $answers = $this->rsvps->forInterviews([$id])[$id] ?? [];
        $members = array_values(array_unique([$interview->interviewerId, ...($this->panel->forInterviews([$id])[$id] ?? [])]));
        $coming = array_values(array_filter($members, static fn (int $userId): bool => ($answers[$userId]->rsvp ?? Rsvp::Pending) !== Rsvp::Declined));

        if ($coming === []) {
            return false;
        }

        $this->notifier->send(
            $coming,
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
