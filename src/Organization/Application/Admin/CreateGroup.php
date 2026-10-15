<?php

namespace Src\Organization\Application\Admin;

use Src\Organization\Application\Group\GroupView;
use Src\Organization\Application\Group\LegalEntityInput;
use Src\Organization\Application\Group\LegalEntityRules;
use Src\Organization\Application\Group\LegalEntityViews;
use Src\Organization\Domain\Events\GroupCreated;
use Src\Organization\Domain\Group;
use Src\Organization\Domain\GroupRepository;
use Src\Organization\Domain\LegalEntity;
use Src\Organization\Domain\LegalEntityRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A platform admin creates a customer group with its first legal entity (usually the holding). The tenant of that entity is created or linked next, and a first
 * group role is given to one of its members (`LinkTenant`, `SetGroupRole`).
 */
final readonly class CreateGroup
{
    public function __construct(
        private GroupRepository $groups,
        private LegalEntityRepository $entities,
        private LegalEntityRules $rules,
        private LegalEntityViews $views,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied when the actor is not an admin
     * @throws StateConflict `group-name-taken` or `legal-entity-name-taken`
     * @throws BusinessRuleViolation `invalid-location`
     */
    public function execute(Actor $actor, string $name, LegalEntityInput $first, ?string $ipAddress, ?string $userAgent): GroupView
    {
        AdminGuard::assert($actor, 'create a group');

        if ($this->groups->nameTaken($name)) {
            throw new StateConflict('A group with this name already exists.', 'group-name-taken');
        }

        [$group, $entity] = $this->transactions->run(function () use ($name, $first): array {
            $group = $this->groups->create(Group::create($name));
            $draft = new LegalEntity(
                null, (int) $group->id, $first->legalName, $first->legalForm, $first->npwp, $first->nib, $first->addressLine,
                $first->provinceId, $first->cityId, $first->subdistrictId, $first->villageId, $first->postalCodeId,
            );
            $this->rules->assertValid($draft);

            return [$group, $this->entities->create($draft)];
        });

        $this->events->dispatch([new GroupCreated((int) $group->id, (int) $entity->id, $name, (int) $actor->id, true, $ipAddress, $userAgent, $this->clock->now())]);

        return new GroupView($group, $this->views->of([$entity]), 0);
    }
}
