<?php

namespace Src\Organization\Application\Admin;

use Src\Organization\Domain\Events\TenantLinkedToLegalEntity;
use Src\Organization\Domain\GroupRepository;
use Src\Organization\Domain\LegalEntityRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\TenantRegistry;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A platform admin links an existing company to a legal entity of a group: from then on the company is an internal tenant (and counts as verified). One entity has
 * one tenant and one company has one entity. A suspended group takes no new tenant: restore it first.
 */
final readonly class LinkTenant
{
    public function __construct(
        private GroupRepository $groups,
        private LegalEntityRepository $entities,
        private TenantRegistry $tenants,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound the group, the entity of that group or the company is missing
     * @throws StateConflict `group-suspended`, `legal-entity-inactive`, `legal-entity-has-tenant` or `company-already-linked`
     */
    public function execute(Actor $actor, int $groupId, int $legalEntityId, int $companyId, ?string $ipAddress, ?string $userAgent): void
    {
        AdminGuard::assert($actor, 'link a tenant');

        $this->transactions->run(function () use ($groupId, $legalEntityId, $companyId): void {
            $this->groups->lock($groupId);
            $group = $this->groups->find($groupId) ?? throw EntityNotFound::of('Group', $groupId);
            $entity = $this->entities->find($legalEntityId);

            if ($entity === null || $entity->groupId !== $groupId) {
                throw EntityNotFound::of('Legal entity', $legalEntityId);
            }

            if (! $this->tenants->exists($companyId)) {
                throw EntityNotFound::of('Company', $companyId);
            }

            if (! $group->isActive()) {
                throw new StateConflict('The group is suspended: restore it before linking a tenant.', 'group-suspended');
            }

            if (! $entity->isActive()) {
                throw new StateConflict('The legal entity is inactive.', 'legal-entity-inactive');
            }

            if ($this->tenants->tenantsOf([$legalEntityId]) !== []) {
                throw new StateConflict('The legal entity already has a tenant.', 'legal-entity-has-tenant');
            }

            if ($this->tenants->legalEntityOf($companyId) !== null) {
                throw new StateConflict('The company is already linked to a legal entity.', 'company-already-linked');
            }

            $this->tenants->link($companyId, $legalEntityId);
        });

        $this->events->dispatch([new TenantLinkedToLegalEntity($groupId, $legalEntityId, $companyId, (int) $actor->id, true, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
