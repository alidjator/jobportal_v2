<?php

namespace Src\Shared\Application\Campaign;

/** Lets Identity ask whether a registration code belongs to a campaign that is running. Provided by Campaign. */
interface CampaignCodes
{
    /**
     * The code in the form it is stored (lower case) when it belongs to a campaign that is active today (Jakarta), otherwise null.
     */
    public function activeCode(?string $code): ?string;
}
