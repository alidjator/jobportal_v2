<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationNoteRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The author deletes a note; a company admin may delete any note of the company (moderation). */
final readonly class DeleteApplicationNote
{
    public function __construct(private TeamApplicationLookup $lookup, private ApplicationNoteRepository $notes) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended`, `company-permission-required` or `not-note-author`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $applicationId, int $noteId): void
    {
        $team = $this->lookup->find($actor, $applicationId, permission: CompanyPermission::ApplicationsReview);
        $note = $this->notes->find($applicationId, $noteId) ?? throw EntityNotFound::of('Note', $noteId);

        if ($note->authorId !== $team->member->userId && ! $team->member->isCompanyAdmin()) {
            throw new AccessDenied('Only the author or a company admin can delete a note.', 'not-note-author');
        }

        $this->notes->delete($noteId);
    }
}
