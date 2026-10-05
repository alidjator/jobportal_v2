<?php

namespace Src\Company\Application\Documents;

use DateTimeImmutable;
use Src\Company\Domain\CompanyDocumentRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\DocumentTypeRepository;
use Src\Company\Domain\Events\CompanyDocumentRejected;
use Src\Company\Domain\RejectionReason;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A platform admin rejects a pending document with a reason code and an optional note (required for `other`), naming the submission they looked at.
 * A pending document never counted for the badge, so rejecting it never changes the badge. The company uploads a new file to try again.
 */
final readonly class RejectCompanyDocument
{
    public function __construct(
        private CompanyRepository $companies,
        private DocumentTypeRepository $types,
        private CompanyDocumentRepository $documents,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the company, the type or the document does not exist
     * @throws StateConflict `document-changed` or `document-not-pending`
     * @throws BusinessRuleViolation `rejection-note-required`
     */
    public function execute(
        Actor $actor,
        int $companyId,
        int $typeId,
        DateTimeImmutable $seenSubmittedAt,
        RejectionReason $reason,
        ?string $note,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can review company documents.');
        }

        $now = $this->clock->now();
        $note = $note === null || trim($note) === '' ? null : trim($note);

        $slug = $this->transactions->run(function () use ($actor, $companyId, $typeId, $seenSubmittedAt, $reason, $note, $now): string {
            $this->companies->lock($companyId);
            $this->companies->findById($companyId) ?? throw EntityNotFound::of('Company', $companyId);
            $type = $this->types->find($typeId) ?? throw EntityNotFound::of('Document type', $typeId);
            $document = $this->documents->find($companyId, $typeId) ?? throw EntityNotFound::of('Document', $typeId);

            if (! $document->isSubmittedAt($seenSubmittedAt)) {
                throw new StateConflict('The company replaced this document after you opened it. Reload and review the latest file.', 'document-changed');
            }

            $this->documents->save($document->reject($reason, $note, (int) $actor->id, $now));

            return $type->slug;
        });

        $this->events->dispatch([new CompanyDocumentRejected($companyId, $typeId, $slug, $reason->value, $note, (int) $actor->id, $ipAddress, $userAgent, $now)]);
    }
}
