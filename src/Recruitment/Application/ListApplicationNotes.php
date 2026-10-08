<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationNoteRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The internal notes of an application, oldest first, for the members who may see its job. */
final readonly class ListApplicationNotes
{
    public function __construct(private TeamApplicationLookup $lookup, private ApplicationNoteRepository $notes, private ApplicationNoteViews $views) {}

    /**
     * @return list<NoteView>
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $applicationId): array
    {
        $this->lookup->find($actor, $applicationId);

        return $this->views->of($this->notes->forApplication($applicationId));
    }
}
