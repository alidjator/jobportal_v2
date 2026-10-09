<?php

namespace Src\Messaging\Domain;

/** An unread stretch whose reminder email is due: the user who has not read the conversation. */
final readonly class UnreadReminder
{
    public function __construct(public int $readStateId, public int $conversationId, public int $userId) {}
}
