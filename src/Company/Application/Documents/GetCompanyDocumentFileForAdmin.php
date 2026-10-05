<?php

namespace Src\Company\Application\Documents;

use Src\Company\Domain\CompanyDocumentRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\DocumentTypeRepository;
use Src\Company\Domain\Events\CompanyDocumentViewed;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A platform admin opens the file of a company's document. Every opening that returns a file is audited (`company.document_viewed`). */
final readonly class GetCompanyDocumentFileForAdmin
{
    public function __construct(
        private CompanyRepository $companies,
        private DocumentTypeRepository $types,
        private CompanyDocumentRepository $documents,
        private FileStorage $files,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the company, the type, the document or its file does not exist
     */
    public function execute(Actor $actor, int $companyId, int $typeId, ?string $ipAddress, ?string $userAgent): DocumentFileContent
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can review company documents.');
        }

        $this->companies->findById($companyId) ?? throw EntityNotFound::of('Company', $companyId);
        $type = $this->types->find($typeId) ?? throw EntityNotFound::of('Document type', $typeId);
        $document = $this->documents->find($companyId, $typeId) ?? throw EntityNotFound::of('Document', $typeId);
        $contents = $this->files->get($document->filePath) ?? throw EntityNotFound::of('Document file', $typeId);

        $this->events->dispatch([new CompanyDocumentViewed($companyId, $typeId, (int) $actor->id, $ipAddress, $userAgent, $this->clock->now())]);

        return new DocumentFileContent($contents, $document->mimeType, $type->slug.'.'.pathinfo($document->filePath, PATHINFO_EXTENSION));
    }
}
