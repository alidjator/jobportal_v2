<?php

namespace Src\Campaign\Application;

use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\ExternalServiceUnavailable;

/** The QR code (SVG) of the registration link of a campaign in the actor's scope. */
final readonly class RenderCampaignQr
{
    public function __construct(
        private CampaignAccess $access,
        private CampaignLinks $links,
        private QrCodes $qrCodes,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-admin-required`
     * @throws EntityNotFound when the campaign is not in the actor's scope
     * @throws ExternalServiceUnavailable `campaign-link-unavailable` while the link template is not configured
     */
    public function execute(Actor $actor, int $campaignId): string
    {
        $campaign = $this->access->campaign($this->access->scope($actor), $campaignId);
        $link = $this->links->forCode($campaign->code) ?? throw new ExternalServiceUnavailable('The registration link is not configured.', 'campaign-link-unavailable');

        return $this->qrCodes->svg($link);
    }
}
