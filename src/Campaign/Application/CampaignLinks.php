<?php

namespace Src\Campaign\Application;

/** The registration link of a campaign, built from the client URL template. */
interface CampaignLinks
{
    /** The full link for a code, or null while the template is not configured. */
    public function forCode(string $code): ?string;
}
