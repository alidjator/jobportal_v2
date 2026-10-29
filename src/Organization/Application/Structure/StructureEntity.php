<?php

namespace Src\Organization\Application\Structure;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\TenantRegistry;
use Src\Shared\Application\Portal\SingleCompany;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * The legal entity a structure call is about. Where there are groups the caller names it. On a `single` installation (`docs/design/erp-installation-type.md`) a member has one legal entity, the one their company is linked to:
 * it is used when none is named, and no other one is reachable.
 */
final readonly class StructureEntity
{
    public function __construct(private SingleCompany $single, private TenantRegistry $tenants) {}

    /**
     * @throws BusinessRuleViolation `legal-entity-required` when none is named and the installation is not `single`
     * @throws EntityNotFound the named entity is not the one of the company, on a `single` installation
     * @throws StateConflict `company-not-linked` when the company of a `single` installation has no legal entity yet
     */
    public function resolve(Actor $actor, ?int $legalEntityId): int
    {
        if ($this->single->enforced() && $actor->membership !== null) {
            $own = $this->tenants->legalEntityOf($actor->membership->companyId)
                ?? throw new StateConflict('The company is not linked to a legal entity yet.', 'company-not-linked');

            if ($legalEntityId !== null && $legalEntityId !== $own) {
                throw EntityNotFound::of('Legal entity', $legalEntityId);
            }

            return $own;
        }

        return $legalEntityId ?? throw new BusinessRuleViolation('The legal entity is required.', 'legal-entity-required', extensions: ['errors' => ['legal_entity_id' => ['The legal entity is required.']]]);
    }
}
