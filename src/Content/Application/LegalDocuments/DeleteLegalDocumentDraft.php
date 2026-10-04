<?php

namespace Src\Content\Application\LegalDocuments;

use Src\Content\Domain\LegalDocumentRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

final readonly class DeleteLegalDocumentDraft
{
    public function __construct(private LegalDocumentRepository $documents) {}

    /**
     * @throws EntityNotFound
     * @throws StateConflict `legal-document-not-draft` when the document is published or archived
     */
    public function execute(int $id): void
    {
        $document = $this->documents->findById($id) ?? throw EntityNotFound::of('Legal document', $id);

        $document->assertDeletable();

        $this->documents->delete($id);
    }
}
