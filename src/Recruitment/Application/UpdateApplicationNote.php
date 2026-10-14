<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationNoteRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Only the author changes the text of a note (a company admin may delete it, never edit it). */
final readonly class UpdateApplicationNote
{
    public function __construct(
        private TeamApplicationLookup $lookup,
        private ApplicationNoteRepository $notes,
        private ApplicationNoteViews $views,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `not-note-author`
     * @throws EntityNotFound
     * @throws BusinessRuleViolation `invalid-note`
     */
    public function execute(Actor $actor, int $applicationId, int $noteId, string $body): NoteView
    {
        $team = $this->lookup->find($actor, $applicationId, permission: CompanyPermission::ApplicationsManage);
        $note = $this->notes->find($applicationId, $noteId) ?? throw EntityNotFound::of('Note', $noteId);

        if ($note->authorId !== $team->member->userId) {
            throw new AccessDenied('Only the author can change a note.', 'not-note-author');
        }

        $edited = $note->edit($body, $this->clock->now());

        if ($edited !== $note) {
            $this->notes->update($edited);
        }

        return $this->views->of([$edited])[0];
    }
}
