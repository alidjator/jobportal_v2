<?php

namespace Src\Platform\Application\Portal;

use Src\Shared\Application\Clock;
use Src\Shared\Application\Portal\PortalBrandValues;
use Src\Shared\Application\Portal\PortalOwner;

/** Keeps a draft of the portal identity (platform) or of a brand (group, company), replacing the one that waited. The draft is not validated until it is published. */
final readonly class SavePortalDraft
{
    public function __construct(private Portals $portals, private Clock $clock) {}

    public function execute(PortalOwner $owner, int $savedBy, PortalIdentity|PortalBrandValues $value): void
    {
        $this->portals->saveDraft($owner, $value->toArray(), $savedBy, $this->clock->now());
    }
}
