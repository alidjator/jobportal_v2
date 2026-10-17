<?php

namespace Src\Recruitment\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Recruitment\Application\NotifyApplicantOfWithdrawal;
use Src\Recruitment\Application\NotifyTeamOfWithdrawal;
use Src\Recruitment\Domain\Events\ApplicationWithdrawn;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Five queued steps on the `default` queue, retried 3 times with a growing delay, after the withdrawal was saved: the job seeker's notice and email (when the
 * team marked it), the team's notice and the interviewers' notice and email. They are separate listeners, so a retry of one never repeats another, and a
 * failure here never undoes the withdrawal.
 */
final class NotifyOfWithdrawalListener implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly NotifyApplicantOfWithdrawal $applicant, private readonly NotifyTeamOfWithdrawal $team) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function applicantInApp(ApplicationWithdrawn $event): void
    {
        $this->applicant->inApp($event);
    }

    public function applicantEmail(ApplicationWithdrawn $event): void
    {
        $this->applicant->email($event);
    }

    public function assignees(ApplicationWithdrawn $event): void
    {
        $this->team->assignees($event);
    }

    public function interviewersInApp(ApplicationWithdrawn $event): void
    {
        $this->team->interviewersInApp($event);
    }

    public function interviewersEmail(ApplicationWithdrawn $event): void
    {
        $this->team->interviewersEmail($event);
    }
}
