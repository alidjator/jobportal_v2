<?php

namespace Src\Organization\Application\Group;

use Src\Organization\Domain\Events\LegalEntityChanged;
use Src\Organization\Domain\LegalEntityRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A group admin edits the data of a legal entity of the group. `$changes` holds only what changes, keyed by property name. */
final readonly class UpdateLegalEntity
{
    public function __construct(
        private GroupAccess $access,
        private LegalEntityRepository $entities,
        private LegalEntityRules $rules,
        private LegalEntityViews $views,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     *
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws EntityNotFound the entity is missing or belongs to another group
     * @throws StateConflict `legal-entity-name-taken`
     * @throws BusinessRuleViolation `invalid-location`
     */
    public function execute(Actor $actor, int $legalEntityId, array $changes, ?string $ipAddress, ?string $userAgent): LegalEntityView
    {
        $context = $this->access->context($actor, GroupPermission::StructureManage);
        $entity = $this->entities->find($legalEntityId);

        if ($entity === null || $entity->groupId !== $context->group->id) {
            throw EntityNotFound::of('Legal entity', $legalEntityId);
        }

        $updated = $entity->with($changes);
        $this->rules->assertValid($updated);
        $this->entities->save($updated);

        $this->events->dispatch([new LegalEntityChanged((int) $context->group->id, $legalEntityId, 'updated', (int) $actor->id, false, $ipAddress, $userAgent, $this->clock->now())]);

        return $this->views->of([$updated])[0];
    }
}
