<?php

namespace Src\Platform\Application\Portal;

use Src\Shared\Application\Portal\PortalOwner;

final readonly class GetPortalForAdmin
{
    public function __construct(private Portals $portals, private PortalHealth $health) {}

    public function execute(): PortalAdminPage
    {
        $live = $this->portals->identity();
        $draft = $this->portals->draft(PortalOwner::platform());

        return new PortalAdminPage(
            $live ?? PortalIdentity::marketplace(),
            $live === null,
            $draft === null ? null : PortalIdentity::fromArray($draft->payload),
            $draft?->savedAt,
            $this->health->skipped($live ?? PortalIdentity::marketplace()),
        );
    }
}
