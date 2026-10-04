<?php

namespace Src\Content\Application\LegalDocuments;

use Src\Content\Domain\LegalDocument;
use Src\Content\Domain\LegalDocumentRepository;
use Src\Content\Domain\LegalDocumentType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Shared\Domain\TranslatedText;

/** Starts a new draft version of the terms or the privacy policy; the version number is the highest existing one plus one. */
final readonly class CreateLegalDocumentDraft
{
    public function __construct(
        private LegalDocumentRepository $documents,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  array<array-key, mixed>  $title
     * @param  array<array-key, mixed>  $body
     *
     * @throws StateConflict `legal-document-version-taken` when another admin created the same version first
     */
    public function execute(Actor $actor, LegalDocumentType $type, array $title, array $body): LegalDocument
    {
        return $this->transactions->run(fn (): LegalDocument => $this->documents->save(
            LegalDocument::draft($type, $this->documents->nextVersion($type), TranslatedText::fromArray($title), TranslatedText::fromArray($body), $actor->id),
        ));
    }
}
