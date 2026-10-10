<?php

namespace Src\Newsletter\Application;

use Src\Newsletter\Domain\Subscriber;
use Src\Shared\Application\Pagination\Page;

/** A page of subscribers with the number of subscribers per status. */
final readonly class SubscriberList
{
    /**
     * @param  Page<Subscriber>  $page
     * @param  array<string, int>  $counts
     */
    public function __construct(public Page $page, public array $counts) {}
}
