<?php

namespace Src\Messaging\Infrastructure\Dashboard;

use Illuminate\Database\ConnectionInterface;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Dashboard\DashboardAudience;
use Src\Shared\Application\Dashboard\DashboardCardProvider;
use Src\Shared\Application\Dashboard\DashboardScope;

/** The conversations with a message the account has not read yet. One card per audience: a company member sees it only with `messages.send`. */
final readonly class UnreadConversationsCard implements DashboardCardProvider
{
    public function __construct(private ConnectionInterface $connection, private DashboardAudience $audience) {}

    public function key(): string
    {
        return 'messages';
    }

    public function audiences(): array
    {
        return [$this->audience];
    }

    public function permission(): ?string
    {
        return $this->audience === DashboardAudience::Company ? CompanyPermission::MessagesSend->value : null;
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
