<?php

namespace Src\Company\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Company\Application\CompanyNotificationType;
use Src\Company\Application\NotifyCompanyAdmins;
use Src\Company\Domain\Events\CompanyNameChangeDecided;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Tells the company admins how an admin decided their name change request. The notice carries the request id, and the note of a rejection; never an admin's
 * name. Runs on the `default` queue, retried 3 times with a growing delay, after the decision was saved.
 */
final class NotifyCompanyAdminsOfNameChange implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly NotifyCompanyAdmins $notify) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(CompanyNameChangeDecided $event): void
    {
        if ($event->decision === CompanyNameChangeDecided::APPROVED) {
            $this->notify->execute($event->companyId, CompanyNotificationType::NAME_CHANGE_APPROVED, ['request_id' => $event->requestId]);

            return;
        }

        $this->notify->execute($event->companyId, CompanyNotificationType::NAME_CHANGE_REJECTED, ['request_id' => $event->requestId, 'note' => $event->note]);
    }
}
