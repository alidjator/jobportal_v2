<?php

namespace Src\Shared\Application;

use Src\Shared\Domain\DomainEvent;

/** Publishes domain events to listeners (possibly in other contexts). Called by a use case after its transaction commits. */
interface EventDispatcher
{
    /** @param  list<DomainEvent>  $events */
    public function dispatch(array $events): void;
}
