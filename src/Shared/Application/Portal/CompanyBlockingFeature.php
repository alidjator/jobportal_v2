<?php

namespace Src\Shared\Application\Portal;

/**
 * Whether a job seeker can block companies on this installation (`docs/design/phase-8-jobseeker-privacy.md` section 3). It exists on the marketplace only (decided 2026-10-31): a company portal
 * shows one company or one group, so there is no other company to block. Where it is off, the endpoints answer 404 `company-blocking-unavailable` and a block that is stored has no effect.
 * Provided by the Platform context, which holds the portal identity.
 */
interface CompanyBlockingFeature
{
    public function enabled(): bool;
}
