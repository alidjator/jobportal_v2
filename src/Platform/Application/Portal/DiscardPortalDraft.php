<?php

namespace Src\Platform\Application\Portal;

use Src\Shared\Application\Portal\PortalOwner;

/** Throws the draft away; the live values stay. Without a draft nothing happens. */
final readonly class DiscardPortalDraft
{
    public function __construct(private Portals $portals) {}

    public function execute(PortalOwner $owner): void
    {
        $this->portals->deleteDraft($owner);
    }
}
