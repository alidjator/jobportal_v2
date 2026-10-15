<?php

namespace Src\Organization\Application\Group;

use Src\Organization\Domain\LegalEntity;

/** A legal entity with the tenant linked to it (null for an entity without recruitment). */
final readonly class LegalEntityView
{
    public function __construct(
        public LegalEntity $entity,
        public ?int $tenantCompanyId,
        public ?string $tenantName,
        public ?string $tenantStatus,
    ) {}
}
