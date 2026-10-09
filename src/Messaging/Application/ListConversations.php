<?php

namespace Src\Messaging\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\CursorPage;
use Src\Shared\Application\Pagination\CursorRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The caller's conversations, the most recent message first: a job seeker's own, or the ones a company member may see. */
final readonly class ListConversations
{
    public function __construct(private ConversationAccess $access, private ConversationReader $reader, private MessagingViews $views) {}

    /**
     * @return CursorPage<ConversationView>
     *
     * @throws AccessDenied `not-a-participant` or `company-suspended`
     */
    public function execute(Actor $actor, CursorRequest $request): CursorPage
    {
        $participant = $this->access->participant($actor);
        $page = $this->reader->page($this->access->audience($participant), $request);

        return new CursorPage($this->views->conversations($page->items, $participant->side), $page->nextCursor, $page->perPage);
    }
}
