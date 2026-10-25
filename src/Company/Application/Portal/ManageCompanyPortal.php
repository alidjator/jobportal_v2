<?php

namespace Src\Company\Application\Portal;

use Src\Company\Application\Team\CompanyAdminGuard;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\Portal\PortalBrandEditor;
use Src\Shared\Application\Portal\PortalBrandPage;
use Src\Shared\Application\Portal\PortalBrandValues;
use Src\Shared\Application\Portal\PortalOwner;
use Src\Shared\Application\Portal\PortalPreview;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * A member who may change the company profile (`company.profile`) keeps the brand of the company's own career site: reads it, keeps a draft, previews it and publishes it.
 * The brand shows on the career site of this one company (scope `company`); a site of a group uses the brand of the group.
 *
 * @throws AccessDenied `not-a-company-member`, `company-permission-required` or `company-suspended`
 */
final readonly class ManageCompanyPortal
{
    public function __construct(private CompanyAdminGuard $guard, private PortalBrandEditor $editor) {}

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
        return PortalOwner::company((int) $this->guard->companyWith($actor, CompanyPermission::CompanyProfile)->id);
    }
}
