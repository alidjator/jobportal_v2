<?php

namespace Src\Company\Infrastructure\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\Company\Application\CompanyNotificationType;
use Src\Company\Domain\Events\CompanyMemberPermissionsChanged;
use Src\Company\Domain\Events\CompanyMemberRenamed;
use Src\Company\Domain\Events\CompanyMemberRoleChanged;
use Src\Shared\Application\Notifications\InAppNotifier;
use Src\Shared\Infrastructure\Queue\QueueName;

/**
 * Tells a member in-app (no email) that their role, permissions or name changed. The change already took effect: permissions are read on every
 * request. Runs on the `default` queue, retried 3 times with a growing delay; the notification carries only the role (or the new name).
 */
final class NotifyMemberOfPermissionChange implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(private readonly InAppNotifier $notifier) {}

    public function viaQueue(): string
    {
        return QueueName::Default->value;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function onRoleChanged(CompanyMemberRoleChanged $event): void
    {
        $this->notifier->send([$event->userId], CompanyNotificationType::PERMISSIONS_CHANGED, ['role' => $event->newRole]);
    }

    public function onPermissionsChanged(CompanyMemberPermissionsChanged $event): void
    {
        $this->notifier->send([$event->userId], CompanyNotificationType::PERMISSIONS_CHANGED, ['role' => 'member']);
    }

    public function onRenamed(CompanyMemberRenamed $event): void
    {
        $this->notifier->send([$event->userId], CompanyNotificationType::MEMBER_RENAMED, ['name' => $event->newName]);
    }
}
