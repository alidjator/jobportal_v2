<?php

namespace Src\Campaign\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Dashboard\DashboardWindow;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** What a campaign in the actor's scope brought: counts only, never the people. */
final readonly class GetCampaignResults
{
    public function __construct(
        private CampaignAccess $access,
        private CampaignStatistics $statistics,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound when the campaign is not in the actor's scope
     */
    public function execute(Actor $actor, int $campaignId): CampaignResults
    {
        $campaign = $this->access->campaign($this->access->scope($actor), $campaignId);

        return $this->statistics->results($campaign, DashboardWindow::at($this->clock->now()));
    }
}
