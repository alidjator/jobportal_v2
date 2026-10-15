<?php

namespace Src\Organization\Application\Group;

use Src\Organization\Domain\Events\LegalEntityCreated;
use Src\Organization\Domain\LegalEntity;
use Src\Organization\Domain\LegalEntityRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A group admin adds a legal entity to the group. The tenant for it, if it recruits, is linked by the platform admin. */
final readonly class CreateLegalEntity
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
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     * @throws StateConflict `legal-entity-name-taken`
     * @throws BusinessRuleViolation `invalid-location`
     */
    public function execute(Actor $actor, LegalEntityInput $input, ?string $ipAddress, ?string $userAgent): LegalEntityView
    {
        $context = $this->access->context($actor, GroupPermission::StructureManage);

        $entity = new LegalEntity(
            null, (int) $context->group->id, $input->legalName, $input->legalForm, $input->npwp, $input->nib, $input->addressLine,
            $input->provinceId, $input->cityId, $input->subdistrictId, $input->villageId, $input->postalCodeId,
        );
        $this->rules->assertValid($entity);

        $created = $this->entities->create($entity);
        $this->events->dispatch([new LegalEntityCreated((int) $context->group->id, (int) $created->id, (int) $actor->id, false, $ipAddress, $userAgent, $this->clock->now())]);

        return $this->views->of([$created])[0];
    }
}
