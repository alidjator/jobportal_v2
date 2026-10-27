<?php

namespace Src\Assessment\Application\Admin;

use Src\Assessment\Application\Team\TeamMemberChanges;
use Src\Assessment\Domain\Events\PsychologistChanged;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

final readonly class RestorePsychologist
{
    public function __construct(
        private TeamMemberChanges $changes,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $psychologistId, ?string $ipAddress, ?string $userAgent): void
    {
        AdminOnly::assert($actor, 'restore a psychologist');

        $this->changes->restore($psychologistId, null);

        $this->events->dispatch([new PsychologistChanged($psychologistId, 'restored', (int) $actor->id, null, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
