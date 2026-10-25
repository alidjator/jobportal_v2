<?php

namespace Src\Organization\Application\Portal;

use Src\Organization\Application\Group\GroupAccess;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Organization\GroupPermission;
use Src\Shared\Application\Portal\PortalBrandEditor;
use Src\Shared\Application\Portal\PortalBrandPage;
use Src\Shared\Application\Portal\PortalBrandValues;
use Src\Shared\Application\Portal\PortalOwner;
use Src\Shared\Application\Portal\PortalPreview;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * A group admin with `group.settings.manage` keeps the brand of the career site of the group: reads it, keeps a draft, previews it and publishes it.
 *
 * @throws AccessDenied `not-a-group-member`, `group-suspended`, `group-permission-required` or, under support access, `support-access-required`
 */
final readonly class ManageGroupPortal
{
    public function __construct(private GroupAccess $access, private PortalBrandEditor $editor) {}

    public function page(Actor $actor): PortalBrandPage
    {
        return $this->editor->page($this->owner($actor));
    }

    public function saveDraft(Actor $actor, PortalBrandValues $values): PortalBrandPage
    {
        return $this->editor->saveDraft($this->owner($actor), (int) $actor->id, $values);
    }

    public function discardDraft(Actor $actor): PortalBrandPage
    {
        return $this->editor->discardDraft($this->owner($actor));
    }

    /** @throws BusinessRuleViolation `portal-draft-missing` */
    public function publish(Actor $actor, ?string $ipAddress, ?string $userAgent): PortalBrandPage
    {
        return $this->editor->publish($this->owner($actor), (int) $actor->id, $ipAddress, $userAgent);
    }

    /** @throws BusinessRuleViolation `portal-draft-missing` */
    public function preview(Actor $actor): PortalPreview
    {
        return $this->editor->preview($this->owner($actor));
    }

    private function owner(Actor $actor): PortalOwner
    {
        return PortalOwner::group((int) $this->access->context($actor, GroupPermission::SettingsManage)->group->id);
    }
}
