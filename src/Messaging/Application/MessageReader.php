<?php

namespace Src\Messaging\Application;

use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;

interface MessageReader
{
    /**
     * The messages of a conversation, newest first.
     *
     * @return CursorPage<MessageRow>
     */
    public function page(int $conversationId, CursorRequest $request): CursorPage;

    public function one(int $messageId): ?MessageRow;

    /**
     * Up to `$limit` messages of the conversation, newest first, starting at `$upToMessageId` (the latest message when null).
     *
     * @return list<MessageRow>
     */
    public function upTo(int $conversationId, ?int $upToMessageId, int $limit): array;
}
