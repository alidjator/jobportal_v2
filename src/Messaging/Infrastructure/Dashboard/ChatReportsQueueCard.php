<?php

namespace Src\Messaging\Infrastructure\Dashboard;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Src\Messaging\Application\MessagingPermissions;
use Src\Messaging\Domain\ReportStatus;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;

/** The chat reports that wait for a decision and when the oldest was filed (`docs/design/phase-8-admin-queue-cards.md`). Counts and a date only, never a message. Needs `messages.moderate`. */
final readonly class ChatReportsQueueCard implements DashboardCardProvider
{
    public function __construct(private ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'chat_reports_queue';
    }

    public function audiences(): array
    {
        return [DashboardAudience::Admin];
    }

    public function permission(): string
    {
        return MessagingPermissions::MESSAGES_MODERATE;
    }

    public function build(DashboardScope $scope): array
    {
        $open = $this->connection->table('messaging_reports')->where('status', ReportStatus::Open->value);
        $oldest = (clone $open)->min('created_at');

        return [
            'open_reports' => $open->count(),
            'oldest_open_at' => $oldest === null ? null : (new DateTimeImmutable((string) $oldest, new DateTimeZone('UTC')))->format(DATE_ATOM),
        ];
    }
}
