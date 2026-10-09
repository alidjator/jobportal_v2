<?php

namespace Src\Messaging\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;

/** The conversations with a message the account has not read yet. */
final readonly class UnreadConversationsCard implements DashboardCardProvider
{
    public function __construct(private ConnectionInterface $connection) {}

    public function key(): string
    {
        return 'messages';
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
        return [
            'unread_conversations' => $this->connection->table('messaging_conversation_reads')
                ->where('user_id', $scope->userId)
                ->whereNotNull('unread_since')
                ->count(),
        ];
    }
}
