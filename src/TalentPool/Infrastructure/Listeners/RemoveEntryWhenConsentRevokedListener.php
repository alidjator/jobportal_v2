<?php

namespace Src\TalentPool\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Recruitment\Domain\Events\TalentPoolConsentRevoked;
use Src\Shared\Infrastructure\Queue\QueueName;
use Src\TalentPool\Application\RemoveEntryWhenConsentRevoked;

/** Runs on the `default` queue, retried 3 times with a growing delay; idempotent. */
final class RemoveEntryWhenConsentRevokedListener implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly RemoveEntryWhenConsentRevoked $remove) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(TalentPoolConsentRevoked $event): void
    {
        $this->remove->execute($event->companyId, $event->applicantUserId);
    }
}
