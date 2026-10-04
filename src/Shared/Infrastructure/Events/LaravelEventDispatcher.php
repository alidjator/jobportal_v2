<?php

namespace Src\Shared\Infrastructure\Events;

use Illuminate\Contracts\Events\Dispatcher;
use Src\Shared\Application\EventDispatcher;

final readonly class LaravelEventDispatcher implements EventDispatcher
{
    public function __construct(private Dispatcher $events) {}

    public function dispatch(array $events): void
    {
        foreach ($events as $event) {
            $this->events->dispatch($event);
        }
    }
}
