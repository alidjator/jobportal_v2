<?php

namespace Src\Recruitment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Recruitment\Application\EmailTeamOfEvents;
use Src\Recruitment\Domain\Events\ApplicationSubmitted;
use Src\Recruitment\Domain\Events\InterviewChanged;
use Src\Recruitment\Domain\Events\OfferChanged;
use Src\Shared\Infrastructure\Queue\QueueName;

/** The emails to the team about an interview answer, an offer answer and a new application. Queued on the `default` queue, retried 3 times with a growing delay, after the change was saved: a failure here never undoes it. */
final class EmailTeamOfEventsListener implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly EmailTeamOfEvents $emails) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function interviewChanged(InterviewChanged $event): void
    {
        $this->emails->interviewChanged($event);
    }

    public function offerChanged(OfferChanged $event): void
    {
        $this->emails->offerAnswered($event);
    }

    public function applicationSubmitted(ApplicationSubmitted $event): void
    {
        $this->emails->applicationReceived($event);
    }
}
