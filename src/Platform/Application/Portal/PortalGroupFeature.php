<?php

namespace Src\Platform\Application\Portal;

use Src\Shared\Application\Portal\GroupFeature;

/** The groups of the ERP are off on a `single` portal (a company portal of one company). Without an identity (the marketplace) they are on. */
final class PortalGroupFeature implements GroupFeature
{
    private ?bool $enabled = null;

    public function __construct(private readonly Portals $portals) {}

    public function enabled(): bool
    {
        return $this->enabled ??= ($this->portals->identity() ?? PortalIdentity::marketplace())->installationType() !== PortalIdentity::TYPE_SINGLE;
    }
}
