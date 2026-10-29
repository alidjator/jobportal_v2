<?php

namespace Src\Platform\Application\Portal;

use Src\Shared\Application\Portal\SingleCompany;

/** The limit of one company holds while the live portal identity is `single`; without an identity (the marketplace) there is none. */
final class PortalSingleCompany implements SingleCompany
{
    private ?bool $enforced = null;

    public function __construct(private readonly Portals $portals) {}

    public function enforced(): bool
    {
        return $this->enforced ??= ($this->portals->identity() ?? PortalIdentity::marketplace())->installationType() === PortalIdentity::TYPE_SINGLE;
    }
}
