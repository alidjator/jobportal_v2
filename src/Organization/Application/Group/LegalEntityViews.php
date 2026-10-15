<?php

namespace Src\Organization\Application\Group;

use Src\Organization\Domain\LegalEntity;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\TenantRegistry;

/** Turns legal entities into views with their tenants, in two reads however many there are. */
final readonly class LegalEntityViews
{
    public function __construct(private TenantRegistry $tenants, private CompanyDirectory $companies) {}

    /**
     * @param  list<LegalEntity>  $entities
     * @return list<LegalEntityView>
     */
    public function of(array $entities): array
    {
        $tenants = $this->tenants->tenantsOf(array_map(static fn (LegalEntity $entity): int => (int) $entity->id, $entities));
        $summaries = $this->companies->summaries(array_values($tenants));

        return array_map(static function (LegalEntity $entity) use ($tenants, $summaries): LegalEntityView {
            $companyId = $tenants[(int) $entity->id] ?? null;
            $summary = $companyId === null ? null : ($summaries[$companyId] ?? null);

            return new LegalEntityView($entity, $companyId, $summary?->name, $summary?->status);
        }, $entities);
    }
}
