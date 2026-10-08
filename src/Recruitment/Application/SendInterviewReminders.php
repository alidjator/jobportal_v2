<?php

namespace Src\Recruitment\Application;

use DateInterval;
use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\Events\InterviewChange;
use Src\Recruitment\Domain\Events\InterviewChanged;
use Src\Recruitment\Domain\Interview;
use Src\Recruitment\Domain\InterviewRepository;
use Src\Recruitment\Domain\InterviewStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;

/**
 * Reminds the job seeker and the team of every confirmed interview that starts within the next 24 hours and was not reminded yet. It runs every hour, so the
 * reminder arrives about a day ahead; an interview confirmed less than a day ahead is reminded on the next run. Each interview is reminded once.
 */
final readonly class SendInterviewReminders
{
    public const int HOURS_AHEAD = 24;

    public function __construct(
        private InterviewRepository $interviews,
        private ApplicationRepository $applications,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /** @return int the number of interviews reminded */
    public function execute(): int
    {
        $now = $this->clock->now();
        $count = 0;

        foreach ($this->interviews->dueForReminder($now, $now->add(new DateInterval('PT'.self::HOURS_AHEAD.'H'))) as $due) {
            $event = $this->transactions->run(function () use ($due, $now): ?InterviewChanged {
                $interview = $this->interviews->active($due->applicationId, forUpdate: true);

                // Changed since it was listed: not confirmed any more, or already reminded.
                if (! $interview instanceof Interview || $interview->id !== $due->id || $interview->status !== InterviewStatus::Confirmed || $interview->remindedAt !== null) {
                    return null;
                }

                $application = $this->applications->find($interview->applicationId);

                if ($application === null) {
                    return null;
                }

                $this->interviews->update($interview->reminded($now));

                return InterviewChanged::of(InterviewChange::Reminder, $application, $interview, $now);
            });

            if ($event instanceof InterviewChanged) {
                $this->events->dispatch([$event]);
                $count++;
            }
        }

        return $count;
    }
}
