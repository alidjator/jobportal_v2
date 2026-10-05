<?php

namespace Src\Company\Application\Documents;

use DateTimeImmutable;
use Src\Company\Application\Verification\BadgeChanges;
use Src\Company\Application\Verification\CompanyVerificationBadge;
use Src\Company\Domain\CompanyDocumentRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\DocumentTypeRepository;
use Src\Company\Domain\Events\CompanyDocumentApproved;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A platform admin approves a pending document, naming the submission they looked at. Approving the last required document gives the company the
 * verification badge in the same transaction. A suspended company can still be reviewed.
 */
final readonly class ApproveCompanyDocument
{
    public function __construct(
        private CompanyRepository $companies,
        private DocumentTypeRepository $types,
        private CompanyDocumentRepository $documents,
        private CompanyVerificationBadge $badge,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the company, the type or the document does not exist
     * @throws StateConflict `document-changed` or `document-not-pending`
     */
    public function execute(Actor $actor, int $companyId, int $typeId, DateTimeImmutable $seenSubmittedAt, ?string $ipAddress, ?string $userAgent): void
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can review company documents.');
        }

        $now = $this->clock->now();

        /** @var array{0: string, 1: BadgeChanges} $result the type slug and the badges that changed */
        $result = $this->transactions->run(function () use ($actor, $companyId, $typeId, $seenSubmittedAt, $now): array {
            // The company row is locked, so a review and an upload of the same company run one after the other.
            $this->companies->lock($companyId);
            $this->companies->findById($companyId) ?? throw EntityNotFound::of('Company', $companyId);
            $type = $this->types->find($typeId) ?? throw EntityNotFound::of('Document type', $typeId);
            $document = $this->documents->find($companyId, $typeId) ?? throw EntityNotFound::of('Document', $typeId);

            if (! $document->isSubmittedAt($seenSubmittedAt)) {
                throw new StateConflict('The company replaced this document after you opened it. Reload and review the latest file.', 'document-changed');
            }

            $this->documents->save($document->approve((int) $actor->id, $now));

            return [$type->slug, $this->badge->recalculateFor($companyId)];
        });

        [$slug, $changes] = $result;

        $this->events->dispatch([
            new CompanyDocumentApproved($companyId, $typeId, $slug, (int) $actor->id, $ipAddress, $userAgent, $now),
            ...$changes->granted($now),
        ]);
    }
}
