<?php

namespace Src\Workforce\Application\Requisition;

use Psr\Log\LoggerInterface;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Workforce\Domain\RequisitionDocumentRepository;
use Src\Workforce\Domain\RequisitionRepository;
use Throwable;

/** Takes a document off a requisition that is not decided yet; the file is deleted with it. */
final readonly class RemoveRequisitionDocument
{
    public function __construct(
        private RequisitionAccess $access,
        private RequisitionRepository $requisitions,
        private RequisitionDocumentRepository $documents,
        private FileStorage $files,
        private RequisitionChangeRecorder $recorder,
        private TransactionManager $transactions,
        private LoggerInterface $logger,
    ) {}

    /**
     * @throws AccessDenied
     * @throws EntityNotFound
     * @throws StateConflict `requisition-documents-closed`
     */
    public function execute(Actor $actor, int $requisitionId, int $documentId, ?string $ipAddress, ?string $userAgent): void
    {
        $scope = $this->access->create($actor);

        [$requisition, $document] = $this->transactions->run(function () use ($scope, $requisitionId, $documentId): array {
            $requisition = $this->requisitions->find($requisitionId, true);

            if ($requisition === null || ! $scope->covers($requisition->legalEntityId)) {
                throw EntityNotFound::of('Requisition', $requisitionId);
            }

            $document = $this->documents->find($requisitionId, $documentId) ?? throw EntityNotFound::of('Document', $documentId);

            if (! $requisition->status->acceptsDocuments()) {
                throw new StateConflict('Documents can be added or removed until the requisition is decided.', 'requisition-documents-closed');
            }

            $this->documents->delete($requisitionId, $documentId);

            return [$requisition, $document];
        });

        try {
            $this->files->delete($document->path);
        } catch (Throwable $e) {
            $this->logger->warning('The file of a requisition document could not be deleted.', ['path' => $document->path, 'exception' => $e]);
        }

        $this->recorder->record($requisition, 'document_removed', $actor->id, $ipAddress, $userAgent, ['document_id' => $documentId, 'name' => $document->name]);
    }
}
