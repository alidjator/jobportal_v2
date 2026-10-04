<?php

namespace Src\Content\Application\LegalDocuments;

use Src\Content\Domain\LegalDocument;
use Src\Content\Domain\LegalDocumentRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** One document by id, in any status (admin). */
final readonly class GetLegalDocument
{
    public function __construct(private LegalDocumentRepository $documents) {}

    /** @throws EntityNotFound */
    public function execute(int $id): LegalDocument
    {
        return $this->documents->findById($id) ?? throw EntityNotFound::of('Legal document', $id);
    }
}
