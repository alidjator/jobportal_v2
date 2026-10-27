<?php

namespace Src\Assessment\Application\Admin;

use Src\Assessment\Application\Team\TeamMemberChanges;
use Src\Assessment\Domain\Events\PsychologistChanged;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Suspends a psychologist: the account is deactivated and its tokens are revoked, so the psychologist is signed out everywhere, and the reports they held are released. */
final readonly class SuspendPsychologist
{
    public function __construct(
        private TeamMemberChanges $changes,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound
     * @throws StateConflict `last-lead`
     */
    public function execute(Actor $actor, int $psychologistId, ?string $ipAddress, ?string $userAgent): void
    {
        AdminOnly::assert($actor, 'suspend a psychologist');

        $this->changes->suspend($psychologistId, null);

        $this->events->dispatch([new PsychologistChanged($psychologistId, 'suspended', (int) $actor->id, null, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
