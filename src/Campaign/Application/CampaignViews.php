<?php

namespace Src\Campaign\Application;

use Src\Campaign\Domain\Campaign;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;

/** Turns campaigns into what is shown: the status of today (Jakarta), the link and the company names (read in one go for a list). */
final readonly class CampaignViews
{
    public function __construct(
        private CompanyDirectory $companies,
        private CampaignLinks $links,
        private Clock $clock,
    ) {}

    /**
     * @param  list<Campaign>  $campaigns
     * @return list<CampaignView>
     */
    public function of(array $campaigns): array
    {
        $today = JakartaToday::of($this->clock->now());
        $companyIds = array_values(array_unique(array_filter(array_map(fn (Campaign $campaign): ?int => $campaign->companyId, $campaigns))));
        $names = $companyIds === [] ? [] : $this->companies->summaries($companyIds);

        return array_map(
            fn (Campaign $campaign): CampaignView => new CampaignView(
                $campaign,
                $campaign->statusOn($today),
                $this->links->forCode($campaign->code),
                $campaign->companyId === null ? null : ($names[$campaign->companyId]->name ?? null),
            ),
            $campaigns,
        );
    }

    public function one(Campaign $campaign): CampaignView
    {
        return $this->of([$campaign])[0];
    }
}
