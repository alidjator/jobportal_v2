<?php

namespace Src\Organization\Application\Group;

use Src\Organization\Domain\Events\LegalEntityChanged;
use Src\Organization\Domain\LegalEntityRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\TenantRegistry;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A group admin deactivates or reactivates a legal entity. An entity whose tenant is active is not deactivated: the platform admin suspends the tenant first.
 * Nothing is deleted.
 */
final readonly class ChangeLegalEntityStatus
{
    public function __construct(
        private GroupAccess $access,
        private LegalEntityRepository $entities,
        private TenantRegistry $tenants,
        private CompanyDirectory $companies,
        private LegalEntityViews $views,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws EntityNotFound the entity is missing or belongs to another group
     * @throws StateConflict `legal-entity-has-active-tenant`, `legal-entity-already-inactive` or `legal-entity-already-active`
     */
    public function execute(Actor $actor, int $legalEntityId, bool $activate, ?string $ipAddress, ?string $userAgent): LegalEntityView
    {
        $context = $this->access->context($actor, GroupPermission::StructureManage);
        $entity = $this->entities->find($legalEntityId);

        if ($entity === null || $entity->groupId !== $context->group->id) {
            throw EntityNotFound::of('Legal entity', $legalEntityId);
        }

        if (! $activate) {
            $tenant = $this->tenants->tenantsOf([$legalEntityId])[$legalEntityId] ?? null;

            if ($tenant !== null && $this->companies->isActive($tenant)) {
                throw new StateConflict('The tenant of this legal entity is active: ask the platform admin to suspend it first.', 'legal-entity-has-active-tenant');
            }
        }

        $changed = $activate ? $entity->activate() : $entity->deactivate();
        $this->entities->save($changed);

        $this->events->dispatch([new LegalEntityChanged((int) $context->group->id, $legalEntityId, $activate ? 'reactivated' : 'deactivated', (int) $actor->id, false, $ipAddress, $userAgent, $this->clock->now())]);

        return $this->views->of([$changed])[0];
    }
}
