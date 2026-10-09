<?php

namespace Src\Messaging\Application;

use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;

interface ConversationReader
{
    /**
     * The conversations the audience sees, most recent message first.
     *
     * @return CursorPage<ConversationRow>
     */
    public function page(ConversationAudience $audience, CursorRequest $request): CursorPage;

    public function one(ConversationAudience $audience, int $conversationId): ?ConversationRow;

    /** In how many of the audience's conversations the user has an unread message. */
    public function unreadCount(ConversationAudience $audience): int;
}
