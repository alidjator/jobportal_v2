<?php

namespace Src\Organization\Application\Group;

use Src\Organization\Domain\LegalEntity;
use Src\Organization\Domain\LegalEntityRepository;
use Src\Shared\Application\References\LocationReferences;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/** What every create and edit of a legal entity must satisfy: a name that is free inside the group and a location that is one matching chain. */
final readonly class LegalEntityRules
{
    public function __construct(private LegalEntityRepository $entities, private LocationReferences $locations) {}

    /**
     * @throws StateConflict `legal-entity-name-taken`
     * @throws BusinessRuleViolation `invalid-location`
     */
    public function assertValid(LegalEntity $entity): void
    {
        if ($this->entities->nameTaken($entity->groupId, $entity->legalName, $entity->id)) {
            throw new StateConflict('The group already has a legal entity with this name.', 'legal-entity-name-taken');
        }

        if (! $this->locations->isValidChain($entity->provinceId, $entity->cityId, $entity->subdistrictId, $entity->villageId, $entity->postalCodeId)) {
            throw new BusinessRuleViolation(
                'The location is not a matching chain: each level must belong to the one above it, and a level needs the levels above it.',
                'invalid-location',
            );
        }
    }
}
