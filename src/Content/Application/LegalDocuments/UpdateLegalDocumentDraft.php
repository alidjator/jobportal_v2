<?php

namespace Src\Content\Application\LegalDocuments;

use Src\Content\Domain\LegalDocument;
use Src\Content\Domain\LegalDocumentRepository;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\TranslatedText;

final readonly class UpdateLegalDocumentDraft
{
    public function __construct(private LegalDocumentRepository $documents) {}

    /**
     * @param  array<array-key, mixed>  $title
     * @param  array<array-key, mixed>  $body
     *
     * @throws EntityNotFound
     * @throws StateConflict `legal-document-not-draft` once the document is published
     */
    public function execute(int $id, array $title, array $body): LegalDocument
    {
        $document = $this->documents->findById($id) ?? throw EntityNotFound::of('Legal document', $id);

        $document->updateDraft(TranslatedText::fromArray($title), TranslatedText::fromArray($body));

        return $this->documents->save($document);
    }
}
