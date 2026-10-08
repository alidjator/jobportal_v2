<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationNote;
use Src\Recruitment\Domain\ApplicationNoteRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A member writes an internal note. It is allowed on an application in any stage, final ones included. */
final readonly class AddApplicationNote
{
    public function __construct(
        private TeamApplicationLookup $lookup,
        private ApplicationNoteRepository $notes,
        private ApplicationNoteViews $views,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-note`
     */
    public function execute(Actor $actor, int $applicationId, string $body): NoteView
    {
        $team = $this->lookup->find($actor, $applicationId);
        $note = $this->notes->add(ApplicationNote::write($applicationId, $team->member->userId, $body, $this->clock->now()));

        return $this->views->of([$note])[0];
    }
}
