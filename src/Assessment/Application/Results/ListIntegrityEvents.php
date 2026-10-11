<?php

namespace Src\Assessment\Application\Results;

use Src\Assessment\Domain\AttemptOutcomeRepository;
use Src\Assessment\Domain\AttemptRepository;
use Src\Assessment\Domain\IntegrityTier;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The integrity log of one test for the members who may see the application: every signal with its time, and the tier. */
final readonly class ListIntegrityEvents
{
    public function __construct(
        private VisibleInvitations $visible,
        private AttemptRepository $attempts,
        private AttemptOutcomeRepository $outcomes,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $invitationId): IntegrityLog
    {
        $this->visible->find($actor, $invitationId);
        $attempt = $this->attempts->findByInvitation($invitationId) ?? throw EntityNotFound::of('Attempt', $invitationId);
        $events = $this->outcomes->eventsOf((int) $attempt->id);

        return new IntegrityLog(IntegrityTier::forCount(count($events)), count($events), $events);
    }
}
