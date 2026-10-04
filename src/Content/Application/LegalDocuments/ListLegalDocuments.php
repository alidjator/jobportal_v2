<?php

namespace Src\Content\Application\LegalDocuments;

use Src\Content\Domain\LegalDocument;
use Src\Content\Domain\LegalDocumentRepository;
use Src\Content\Domain\LegalDocumentType;

/** Every version, drafts included, newest first. Bounded list: not paginated. */
final readonly class ListLegalDocuments
{
    public function __construct(private LegalDocumentRepository $documents) {}

    /** @return list<LegalDocument> */
    public function execute(?LegalDocumentType $type): array
    {
        return $this->documents->list($type);
    }
}
