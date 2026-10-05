<?php

namespace Src\Company\Application\Documents;

use Src\Company\Domain\CompanyDocument;
use Src\Company\Domain\DocumentType;

/** One document type with what the company submitted for it (null when nothing yet), as a platform admin sees it: names in both languages. */
final readonly class AdminDocumentSlot
{
    public function __construct(
        public DocumentType $type,
        public ?CompanyDocument $document,
    ) {}
}
