<?php

namespace Src\Company\Application\Documents;

use Psr\Log\LoggerInterface;
use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\CompanyDocument;
use Src\Company\Domain\CompanyDocumentRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\Events\CompanyDocumentDeleted;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Throwable;

/**
 * A company admin deletes the document of one type while it is pending or rejected. An approved document is never deleted, only replaced by a
 * new upload, so deleting never changes the verification badge. The file is deleted after the row, and a failure only leaves a stray file behind.
 */
final readonly class RemoveCompanyDocument
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private CompanyDocumentRepository $documents,
        private CompanyRepository $companies,
        private FileStorage $files,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws EntityNotFound when the type or the company's document for it does not exist
     * @throws StateConflict `document-approved`
     */
    public function execute(Actor $actor, int $typeId, ?string $ipAddress, ?string $userAgent): void
    {
        $companyId = (int) $this->guard->company($actor)->id;

        /** @var CompanyDocument $document */
        $document = $this->transactions->run(function () use ($companyId, $typeId): CompanyDocument {
            $this->companies->lock($companyId);
            $document = $this->documents->find($companyId, $typeId) ?? throw EntityNotFound::of('Document', $typeId);

            if (! $document->canBeDeleted()) {
                throw new StateConflict('An approved document cannot be deleted; upload a new file to replace it.', 'document-approved');
            }

            $this->documents->delete((int) $document->id);

            return $document;
        });

        try {
            $this->files->delete($document->filePath);
        } catch (Throwable $e) {
            $this->logger->warning('A company document file could not be deleted.', ['path' => $document->filePath, 'exception' => $e]);
        }

        $this->events->dispatch([new CompanyDocumentDeleted($companyId, $typeId, $document->status->value, (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
