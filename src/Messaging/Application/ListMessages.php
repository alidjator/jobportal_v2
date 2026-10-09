<?php

namespace Src\Messaging\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The messages of a conversation the caller may see, newest first. */
final readonly class ListMessages
{
    public function __construct(private ConversationAccess $access, private MessageReader $reader, private MessagingViews $views) {}

    /**
     * @return CursorPage<MessageView>
     *
     * @throws AccessDenied
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $conversationId, CursorRequest $request): CursorPage
    {
        $this->access->open($this->access->participant($actor), $conversationId);
        $page = $this->reader->page($conversationId, $request);

        return new CursorPage($this->views->messages($page->items), $page->nextCursor, $page->perPage);
    }
}
