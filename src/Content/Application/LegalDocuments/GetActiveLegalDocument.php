<?php

namespace Src\Content\Application\LegalDocuments;

use Src\Content\Domain\LegalDocument;
use Src\Content\Domain\LegalDocumentRepository;
use Src\Content\Domain\LegalDocumentType;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The version of the terms or privacy policy that is in force (public). */
final readonly class GetActiveLegalDocument
{
    public function __construct(private LegalDocumentRepository $documents) {}

    /** @throws EntityNotFound when no version of this type was published yet */
    public function execute(LegalDocumentType $type): LegalDocument
    {
        return $this->documents->findPublished($type) ?? throw EntityNotFound::of('Legal document', $type->value);
    }
}
