<?php

namespace Src\Assessment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Assessment\Application\Reports\NotifyOfReportSigned;
use Src\Assessment\Domain\Events\ReportRevised;
use Src\Assessment\Domain\Events\ReportSigned;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Three queued steps on the `default` queue, retried 3 times with a growing delay, after the signature was saved: the team's in-app notification and the candidate's in-app notification and email. A failure here never undoes the signature. */
final class NotifyOfReportSignedListener implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly NotifyOfReportSigned $notify) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function inApp(ReportSigned $event): void
    {
        $this->notify->inApp($event);
    }

    public function candidateInApp(ReportSigned $event): void
    {
        $this->notify->candidateInApp($event);
    }

    public function candidateEmail(ReportSigned $event): void
    {
        $this->notify->candidateEmail($event);
    }

    public function revisedInApp(ReportRevised $event): void
    {
        $this->notify->revisedInApp($event);
    }

    public function revisedCandidateInApp(ReportRevised $event): void
    {
        $this->notify->revisedCandidateInApp($event);
    }

    public function revisedCandidateEmail(ReportRevised $event): void
    {
        $this->notify->revisedCandidateEmail($event);
    }
}
