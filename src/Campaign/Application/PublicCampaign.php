<?php

namespace Src\Campaign\Application;

use Src\Campaign\Domain\CampaignType;
use Src\Shared\Application\Company\CompanySummary;

/** What the registration page shows about a running campaign. */
final readonly class PublicCampaign
{
    public function __construct(
        public string $code,
        public string $name,
        public CampaignType $type,
        public ?CompanySummary $company,
    ) {}
}
