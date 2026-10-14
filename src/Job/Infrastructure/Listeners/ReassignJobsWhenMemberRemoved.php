<?php

namespace Src\Job\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Company\Domain\Events\CompanyMemberRemoved;
use Src\Job\Application\ReassignJobsOfRemovedMember;
use Src\Shared\Infrastructure\Queue\QueueName;

/** Runs on the `default` queue, retried 3 times with a growing delay; running it again changes nothing. */
final class ReassignJobsWhenMemberRemoved implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly ReassignJobsOfRemovedMember $reassign) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(CompanyMemberRemoved $event): void
    {
        $this->reassign->execute($event->companyId, $event->userId, $event->occurredAt(), $event->transferToUserId);
    }
}
