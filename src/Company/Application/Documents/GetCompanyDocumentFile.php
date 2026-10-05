<?php

namespace Src\Company\Application\Documents;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\CompanyDocumentRepository;
use Src\Company\Domain\DocumentTypeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A company admin reads the file of the company's own document. Nobody else's: the type and the company come from the caller. */
final readonly class GetCompanyDocumentFile
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private DocumentTypeRepository $types,
        private CompanyDocumentRepository $documents,
        private FileStorage $files,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     * @throws EntityNotFound when the type, the document or its file does not exist
     */
    public function execute(Actor $actor, int $typeId): DocumentFileContent
    {
        $companyId = (int) $this->guard->company($actor)->id;

        $type = $this->types->find($typeId) ?? throw EntityNotFound::of('Document type', $typeId);
        $document = $this->documents->find($companyId, $typeId) ?? throw EntityNotFound::of('Document', $typeId);
        $contents = $this->files->get($document->filePath) ?? throw EntityNotFound::of('Document file', $typeId);

        return new DocumentFileContent($contents, $document->mimeType, $type->slug.'.'.pathinfo($document->filePath, PATHINFO_EXTENSION));
    }
}
