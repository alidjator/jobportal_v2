<?php

namespace Src\Workforce\Application\Requisition;

use Psr\Log\LoggerInterface;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;
use Src\Workforce\Domain\RequisitionDocument;
use Src\Workforce\Domain\RequisitionDocumentRepository;
use Src\Workforce\Domain\RequisitionRepository;
use Throwable;

/** A member with `workforce.requisitions.create` attaches a document to a requisition that is not decided yet (a draft, a rejected one or one waiting for approval). */
final readonly class AddRequisitionDocument
{
    private const string DIRECTORY = 'workforce/requisition-documents';

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
     * @throws BusinessRuleViolation `invalid-file` or `too-many-documents`
     */
    public function execute(Actor $actor, int $requisitionId, string $contents, string $name, ?string $ipAddress, ?string $userAgent): RequisitionDocument
    {
        $scope = $this->access->create($actor);
        $type = RequisitionDocumentFile::check($contents, $name);
        $path = self::DIRECTORY.'/'.bin2hex(random_bytes(16)).'.'.RequisitionDocumentFile::extension($type);

        try {
            [$requisition, $document] = $this->transactions->run(function () use ($scope, $requisitionId, $contents, $name, $type, $path, $actor): array {
                $requisition = $this->requisitions->find($requisitionId, true);

                if ($requisition === null || ! $scope->covers($requisition->legalEntityId)) {
                    throw EntityNotFound::of('Requisition', $requisitionId);
                }

                if (! $requisition->status->acceptsDocuments()) {
                    throw new StateConflict('Documents can be added or removed until the requisition is decided.', 'requisition-documents-closed');
                }

                if ($this->documents->count($requisitionId) >= RequisitionDocumentFile::MAX_PER_REQUISITION) {
                    throw new BusinessRuleViolation('A requisition holds at most :max documents.', 'too-many-documents', parameters: ['max' => RequisitionDocumentFile::MAX_PER_REQUISITION]);
                }

                $document = $this->documents->create(new RequisitionDocument(null, $requisitionId, RequisitionDocumentFile::cleanName($name), $path, $type, strlen($contents), (int) $actor->id));
                $this->files->put($path, $contents);

                return [$requisition, $document];
            });
        } catch (Throwable $e) {
            $this->deleteQuietly($path);

            throw $e;
        }

        $this->recorder->record($requisition, 'document_added', $actor->id, $ipAddress, $userAgent, ['document_id' => $document->id, 'name' => $document->name]);

        return $document;
    }

    private function deleteQuietly(string $path): void
    {
        try {
            $this->files->delete($path);
        } catch (Throwable $e) {
            $this->logger->warning('The file of a requisition document could not be deleted.', ['path' => $path, 'exception' => $e]);
        }
    }
}
