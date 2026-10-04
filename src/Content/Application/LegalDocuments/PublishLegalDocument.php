<?php

namespace Src\Content\Application\LegalDocuments;

use Src\Content\Domain\Events\LegalDocumentPublished;
use Src\Content\Domain\LegalDocument;
use Src\Content\Domain\LegalDocumentRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * Publishes a draft: it becomes the active version of its type, locked for good, and the previously active version is archived,
 * all in one transaction. The type is locked first, so two admins cannot publish two versions at once.
 */
final readonly class PublishLegalDocument
{
    public function __construct(
        private LegalDocumentRepository $documents,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws EntityNotFound
     * @throws StateConflict `legal-document-not-draft`
     * @throws BusinessRuleViolation `legal-document-incomplete` when the title or body lacks a language
     */
    public function execute(Actor $actor, int $id): LegalDocument
    {
        $now = $this->clock->now();

        $published = $this->transactions->run(function () use ($id, $now): LegalDocument {
            $document = $this->documents->findById($id) ?? throw EntityNotFound::of('Legal document', $id);

            $this->documents->lockType($document->type);

            // Read again after the lock, so a concurrent publication is seen.
            $document = $this->documents->findById($id) ?? throw EntityNotFound::of('Legal document', $id);
            $previous = $this->documents->findPublished($document->type);

            $document->publish($now);

            if ($previous !== null) {
                $previous->archive();
                $this->documents->save($previous);
            }

            return $this->documents->save($document);
        });

        $this->events->dispatch([new LegalDocumentPublished((int) $published->id, $published->type, $published->version, $actor->id, $now)]);

        return $published;
    }
}
