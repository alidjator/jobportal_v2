<?php

namespace Src\Campaign\Application;

use Src\Campaign\Domain\Campaign;
use Src\Campaign\Domain\CampaignStatus;

/** A campaign as it is shown: its status today, the registration link (null while it is not configured) and the name of its company (null for the platform). */
final readonly class CampaignView
{
    public function __construct(
        public Campaign $campaign,
        public CampaignStatus $status,
        public ?string $link,
        public ?string $companyName,
    ) {}
}
