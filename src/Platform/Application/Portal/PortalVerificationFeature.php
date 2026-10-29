<?php

namespace Src\Platform\Application\Portal;

use Src\Shared\Application\Portal\VerificationFeature;

/** The verification of companies belongs to `multi` of independent companies, the marketplace, which is also what a portal without an identity is. */
final class PortalVerificationFeature implements VerificationFeature
{
    private ?bool $enabled = null;

    public function __construct(private readonly Portals $portals) {}

    public function enabled(): bool
    {
        return $this->enabled ??= ($this->portals->identity() ?? PortalIdentity::marketplace())->multiKind() === PortalIdentity::KIND_INDEPENDENT;
    }
}
