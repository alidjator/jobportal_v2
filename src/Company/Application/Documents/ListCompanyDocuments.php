<?php

namespace Src\Company\Application\Documents;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Company\Domain\CompanyDocumentRepository;
use Src\Company\Domain\DocumentTypeRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** A company admin sees every active document type with the company's document for it, and whether the company is verified. */
final readonly class ListCompanyDocuments
{
    public function __construct(
        private CompanyAdminGuard $guard,
        private DocumentTypeRepository $types,
        private CompanyDocumentRepository $documents,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-admin-required` or `company-suspended`
     */
    public function execute(Actor $actor, string $language): CompanyDocumentsOverview
    {
        $company = $this->guard->company($actor);

        $byType = [];

        foreach ($this->documents->ofCompany((int) $company->id) as $document) {
            $byType[$document->documentTypeId] = $document;
        }

        $slots = [];

        foreach ($this->types->all() as $type) {
            if ($type->isActive) {
                $slots[] = new CompanyDocumentSlot($type, $type->name->resolve($language), $byType[(int) $type->id] ?? null);
            }
        }

        return new CompanyDocumentsOverview($slots, $company->verifiedAt() !== null);
    }
}
