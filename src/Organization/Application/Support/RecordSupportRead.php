<?php

namespace Src\Organization\Application\Support;

use Src\Organization\Domain\Events\SupportRead;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;

/** Notes that a platform admin read data of a group under its support access, so the read is audited and shows in the group activity log. */
final readonly class RecordSupportRead
{
    public function __construct(private EventDispatcher $events, private Clock $clock) {}

    public function execute(Actor $actor, string $request, ?string $ipAddress, ?string $userAgent): void
    {
        if ($actor->id === null || $actor->supportGroupId === null) {
            return;
        }

        $this->events->dispatch([new SupportRead($actor->supportGroupId, $actor->id, $request, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
