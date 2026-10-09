<?php

namespace Src\Notification\Infrastructure\Dashboard;

use Src\Notification\Application\InApp\InAppNotificationReader;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;

/** The number behind the notification badge. */
final readonly class UnreadNotificationsCard implements DashboardCardProvider
{
    public function __construct(private InAppNotificationReader $reader) {}

    public function key(): string
    {
        return 'notifications';
    }

    public function audiences(): array
    {
        return [DashboardAudience::JobSeeker, DashboardAudience::Company];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function build(DashboardScope $scope): array
    {
        return ['unread' => $this->reader->unreadCount($scope->userId)];
    }
}
