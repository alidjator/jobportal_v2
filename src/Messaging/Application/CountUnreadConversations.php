<?php

namespace Src\Messaging\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** In how many conversations the caller has an unread message (for the badge). */
final readonly class CountUnreadConversations
{
    public function __construct(private ConversationAccess $access, private ConversationReader $reader) {}

    /** @throws AccessDenied */
    public function execute(Actor $actor): int
    {
        return $this->reader->unreadCount($this->access->audience($this->access->participant($actor)));
    }
}
