<?php

namespace Src\Company\Application\Documents;

use Src\Company\Domain\CompanyDocument;
use Src\Company\Domain\DocumentType;

/** One active document type with what the company submitted for it (null when nothing yet). `typeName` is in the request language. */
final readonly class CompanyDocumentSlot
{
    public function __construct(
        public DocumentType $type,
        public string $typeName,
        public ?CompanyDocument $document,
    ) {}
}
