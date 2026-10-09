<?php

namespace Src\Campaign\Application;

use Src\Campaign\Domain\CampaignRepository;
use Src\Campaign\Domain\CampaignStatus;
use Src\Shared\Application\Campaign\CampaignCodes;
use Src\Shared\Application\Clock;

/** Answers Identity: a registration code counts only when its campaign is active today (Jakarta). */
final readonly class RunningCampaignCodes implements CampaignCodes
{
    public function __construct(
        private CampaignRepository $campaigns,
        private Clock $clock,
    ) {}

    public function activeCode(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $campaign = $this->campaigns->findByCode(mb_strtolower(trim($code)));

        return $campaign?->statusOn(JakartaToday::of($this->clock->now())) === CampaignStatus::Active ? $campaign->code : null;
    }
}
