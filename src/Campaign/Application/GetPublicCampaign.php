<?php

namespace Src\Campaign\Application;

use Src\Campaign\Domain\CampaignRepository;
use Src\Campaign\Domain\CampaignStatus;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** A campaign that is running today, read by its code, for the registration page. A campaign that is off, not started or over does not exist for the public. */
final readonly class GetPublicCampaign
{
    public function __construct(
        private CampaignRepository $campaigns,
        private CompanyDirectory $companies,
        private Clock $clock,
    ) {}

    /** @throws EntityNotFound */
    public function execute(string $code): PublicCampaign
    {
        $campaign = $this->campaigns->findByCode(mb_strtolower(trim($code)));

        if ($campaign === null || $campaign->statusOn(JakartaToday::of($this->clock->now())) !== CampaignStatus::Active) {
            throw EntityNotFound::of('Campaign', $code);
        }

        return new PublicCampaign($campaign->code, $campaign->name, $campaign->type, $campaign->companyId === null ? null : ($this->companies->summaries([$campaign->companyId])[$campaign->companyId] ?? null));
    }
}
