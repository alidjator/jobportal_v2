<?php

namespace Src\Shared\Application\Company;

use DateTimeImmutable;

/**
 * The link between a tenant (a company) and a legal entity of the Organization context, and the group-wide suspension of tenants. Provided by Company, which owns the
 * tenant rows; Organization decides who may call it. A tenant with a link is an internal tenant (`docs/design/erp-organization.md` section 2).
 */
interface TenantRegistry
{
    /** The legal entity the company is linked to, or null for an external tenant (or a company that does not exist). */
    public function legalEntityOf(int $companyId): ?int;

    /**
     * The tenant of each of these legal entities, keyed by legal entity id; an entity without a tenant is missing.
     *
     * @param  list<int>  $legalEntityIds
     * @return array<int, int>
     */
    public function tenantsOf(array $legalEntityIds): array;

    /** How many companies are linked to a legal entity, in the whole installation. */
    public function linkedCount(): int;

    /** Whether the company exists. */
    public function exists(int $companyId): bool;

    /** Links the company to the legal entity; the caller has checked that both are free. */
    public function link(int $companyId, int $legalEntityId): void;

    public function unlink(int $companyId): void;

    /**
     * Suspends the active tenants among these companies for the group: they get the company suspension and are marked, so a restore of the group brings back only them.
     *
     * @param  list<int>  $companyIds
     */
    public function suspendForGroup(array $companyIds, string $reason, DateTimeImmutable $at): void;

    /**
     * Restores the tenants among these companies that the group suspension suspended; a tenant suspended on its own stays suspended.
     *
     * @param  list<int>  $companyIds
     */
    public function restoreFromGroup(array $companyIds): void;
}
