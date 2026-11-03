<?php

namespace Src\Workforce\Application\Requisition;

use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Workforce\Application\Plan\PlanSide;
use Src\Workforce\Domain\RequisitionDocumentRepository;
use Src\Workforce\Domain\RequisitionRepository;

/** The bytes of a document, for whoever may read the requisition: the tenant's readers and the group's. */
final readonly class GetRequisitionDocument
{
    public function __construct(
        private RequisitionAccess $access,
        private RequisitionRepository $requisitions,
        private RequisitionDocumentRepository $documents,
        private FileStorage $files,
    ) {}

    /**
     * @throws AccessDenied
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, PlanSide $side, int $requisitionId, int $documentId): RequisitionDocumentContent
    {
        $scope = $this->access->read($actor, $side);
        $requisition = $this->requisitions->find($requisitionId);

        if ($requisition === null || ! $scope->covers($requisition->legalEntityId)) {
            throw EntityNotFound::of('Requisition', $requisitionId);
        }

        $document = $this->documents->find($requisitionId, $documentId) ?? throw EntityNotFound::of('Document', $documentId);
        $contents = $this->files->get($document->path) ?? throw EntityNotFound::of('Document', $documentId);

        return new RequisitionDocumentContent($document->name, $document->contentType, $contents);
    }
}
