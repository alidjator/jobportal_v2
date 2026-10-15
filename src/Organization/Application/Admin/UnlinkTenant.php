<?php

namespace Src\Organization\Application\Admin;

use Src\Organization\Domain\Events\TenantUnlinkedFromLegalEntity;
use Src\Organization\Domain\GroupMemberRepository;
use Src\Organization\Domain\GroupRepository;
use Src\Organization\Domain\LegalEntityRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyMembershipResolver;
use Src\Shared\Application\Company\TenantRegistry;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A platform admin removes the link between a tenant and its legal entity. Allowed only while nothing of the ERP depends on the link: today the group roles of
 * the tenant's members (requisitions and employees will count too); otherwise 409 `tenant-link-in-use`.
 */
final readonly class UnlinkTenant
{
    public function __construct(
        private GroupRepository $groups,
        private LegalEntityRepository $entities,
        private GroupMemberRepository $members,
        private CompanyMembershipResolver $memberships,
        private TenantRegistry $tenants,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws EntityNotFound the group, the entity of that group or its tenant is missing
     * @throws StateConflict `tenant-link-in-use`
     */
    public function execute(Actor $actor, int $groupId, int $legalEntityId, ?string $ipAddress, ?string $userAgent): void
    {
        AdminGuard::assert($actor, 'unlink a tenant');

        $companyId = $this->transactions->run(function () use ($groupId, $legalEntityId): int {
            $this->groups->lock($groupId);
            $entity = $this->entities->find($legalEntityId);

            if ($entity === null || $entity->groupId !== $groupId) {
                throw EntityNotFound::of('Legal entity', $legalEntityId);
            }

            $companyId = $this->tenants->tenantsOf([$legalEntityId])[$legalEntityId] ?? throw EntityNotFound::of('Tenant', $legalEntityId);

            foreach ($this->members->ofGroup($groupId) as $member) {
                if ($this->memberships->forUser($member->userId)?->companyId === $companyId) {
                    throw new StateConflict('Members of this tenant hold group roles: remove them first.', 'tenant-link-in-use');
                }
            }

            $this->tenants->unlink($companyId);

            return $companyId;
        });

        $this->events->dispatch([new TenantUnlinkedFromLegalEntity($groupId, $legalEntityId, $companyId, (int) $actor->id, true, $ipAddress, $userAgent, $this->clock->now())]);
    }
}
