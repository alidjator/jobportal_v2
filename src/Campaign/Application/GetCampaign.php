<?php

namespace Src\Campaign\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Reads one campaign in the actor's scope. */
final readonly class GetCampaign
{
    public function __construct(
        private CampaignAccess $access,
        private CampaignViews $views,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound when the campaign is not in the actor's scope
     */
    public function execute(Actor $actor, int $campaignId): CampaignView
    {
        return $this->views->one($this->access->campaign($this->access->scope($actor), $campaignId));
    }
}
