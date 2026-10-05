<?php

namespace Src\Company\Application\Documents;

use Src\Company\Domain\CompanyDocumentRepository;
use Src\Company\Domain\CompanyRepository;
use Src\Company\Domain\DocumentTypeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A platform admin reads the documents of any company, also while it is suspended. */
final readonly class ListCompanyDocumentsForAdmin
{
    public function __construct(
        private CompanyRepository $companies,
        private DocumentTypeRepository $types,
        private CompanyDocumentRepository $documents,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound when the company does not exist
     */
    public function execute(Actor $actor, int $companyId): AdminDocumentsOverview
    {
        if (! $actor->isAdmin()) {
            throw new AccessDenied('Only an administrator can review company documents.');
        }

        $company = $this->companies->findById($companyId) ?? throw EntityNotFound::of('Company', $companyId);

        $byType = [];

        foreach ($this->documents->ofCompany($companyId) as $document) {
            $byType[$document->documentTypeId] = $document;
        }

        $slots = [];

        foreach ($this->types->all() as $type) {
            $document = $byType[(int) $type->id] ?? null;

            if ($type->isActive || $document !== null) {
                $slots[] = new AdminDocumentSlot($type, $document);
            }
        }

        return new AdminDocumentsOverview($slots, $company->verifiedAt() !== null);
    }
}
