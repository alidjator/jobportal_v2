<?php

namespace Src\Shared\Application\Portal;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * What the Company and Organization contexts need from Platform for the brand screen of a company or group admin: read the brand, keep a draft, preview it and publish it.
 * The caller has already checked who may act for the owner. Provided by Platform.
 */
interface PortalBrandEditor
{
    public function page(PortalOwner $owner): PortalBrandPage;

    /** Keeps the draft (the whole brand), replacing the one that waited. */
    public function saveDraft(PortalOwner $owner, int $userId, PortalBrandValues $values): PortalBrandPage;

    public function discardDraft(PortalOwner $owner): PortalBrandPage;

    /**
     * Makes the draft the live brand, deletes the draft and audits the values before and after.
     *
     * @throws BusinessRuleViolation `portal-draft-missing`
     */
    public function publish(PortalOwner $owner, int $userId, ?string $ipAddress, ?string $userAgent): PortalBrandPage;

    /** @throws BusinessRuleViolation `portal-draft-missing` */
    public function preview(PortalOwner $owner): PortalPreview;
}
