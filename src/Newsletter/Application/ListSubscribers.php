<?php

namespace Src\Newsletter\Application;

use Src\Newsletter\Domain\SubscriberRepository;
use Src\Newsletter\Domain\SubscriberStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The subscribers, newest first, with the count per status (of the whole list, not of the filter). */
final readonly class ListSubscribers
{
    public function __construct(private SubscriberRepository $subscribers) {}

    /** @throws AccessDenied `admin-only` */
    public function execute(Actor $actor, ?string $search, ?SubscriberStatus $status, PageRequest $request): SubscriberList
    {
        NewsletterAccess::assertAdmin($actor);

        return new SubscriberList($this->subscribers->page($search, $status, $request), $this->subscribers->countsByStatus());
    }
}
