<?php

namespace Src\Platform\Application\Portal;

use Src\Shared\Application\Portal\CompanyBlockingFeature;

/** Blocking companies belongs to the marketplace, which is also what a portal without an identity is. */
final class PortalCompanyBlockingFeature implements CompanyBlockingFeature
{
    private ?bool $enabled = null;

    public function __construct(private readonly Portals $portals) {}

    public function enabled(): bool
    {
        return $this->enabled ??= ($this->portals->identity() ?? PortalIdentity::marketplace())->isMarketplace();
    }
}
