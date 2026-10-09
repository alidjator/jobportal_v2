<?php

namespace Src\Campaign\Application;

use Src\Campaign\Domain\Campaign;
use Src\Shared\Application\Dashboard\DashboardWindow;

/** The read-only counts of a campaign. The implementation reads the accounts and the applications directly, a documented exception for reporting that never writes. */
interface CampaignStatistics
{
    /** How many accounts registered with this code (the code of an imported account may differ in letter case). */
    public function registrations(string $code): int;

    public function results(Campaign $campaign, DashboardWindow $window): CampaignResults;
}
