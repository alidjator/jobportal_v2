<?php

namespace Src\Company\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Company\Application\CompanyNotificationType;
use Src\Identity\Domain\Events\AccountTwoFactorResetByAdmin;
use Src\Shared\Application\Company\CompanyMembershipResolver;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Tells a company member in-app (the email is sent by Identity) that a platform admin reset their second factor. The reset ended every session, so the notice waits for the next sign-in.
 * An account that belongs to no company (a job seeker) gets no company notice. Runs on the `default` queue, retried 3 times with a growing delay.
 */
final class NotifyMemberOfAdminTwoFactorReset implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly InAppNotifier $notifier, private readonly CompanyMembershipResolver $memberships) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(AccountTwoFactorResetByAdmin $event): void
    {
        if ($this->memberships->forUser($event->userId) !== null) {
            $this->notifier->send([$event->userId], CompanyNotificationType::TWO_FACTOR_RESET, []);
        }
    }
}
