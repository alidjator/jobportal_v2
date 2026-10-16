<?php

namespace Src\Organization\Application\Reading;

use Src\Organization\Application\Group\GroupAccess;
use Src\Organization\Application\Group\LegalEntityView;
use Src\Organization\Application\Group\LegalEntityViews;
use Src\Organization\Domain\LegalEntityRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The internal tenants of the group (legal entities that have a tenant), for a member who may read their recruitment (`group.recruitment.view`). */
final readonly class ListGroupTenants
{
    public function __construct(private GroupAccess $access, private LegalEntityRepository $entities, private LegalEntityViews $views) {}

    /**
     * @return list<LegalEntityView> by legal entity name
     *
     * @throws AccessDenied `not-a-group-member`, `group-suspended` or `group-permission-required`
     */
    public function execute(Actor $actor): array
    {
        $context = $this->access->context($actor, GroupPermission::RecruitmentView);

        return array_values(array_filter(
            $this->views->of($this->entities->ofGroup((int) $context->group->id)),
            static fn (LegalEntityView $view): bool => $view->tenantCompanyId !== null,
        ));
    }
}
