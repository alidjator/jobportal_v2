<?php

namespace Src\Content\Application\LegalDocuments;

use Src\Content\Domain\LegalDocument;
use Src\Content\Domain\LegalDocumentRepository;
use Src\Content\Domain\LegalDocumentType;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A specific published or archived version (public), so people can read the text they accepted. Drafts are never shown. */
final readonly class GetLegalDocumentVersion
{
    public function __construct(private LegalDocumentRepository $documents) {}

    /** @throws EntityNotFound when the version does not exist or is still a draft */
    public function execute(LegalDocumentType $type, int $version): LegalDocument
    {
        $document = $this->documents->findVersion($type, $version);

        return $document !== null && $document->isVisibleToPublic()
            ? $document
            : throw EntityNotFound::of('Legal document', "{$type->value} v{$version}");
    }
}
