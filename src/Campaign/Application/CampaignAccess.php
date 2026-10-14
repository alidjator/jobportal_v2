<?php

namespace Src\Campaign\Application;

use Src\Campaign\Domain\Campaign;
use Src\Campaign\Domain\CampaignRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The checks every campaign action starts with. A platform admin handles every campaign (the route already requires `campaigns.manage`); a company handles its own
 * only, and only through a member of an active company who holds `campaigns.manage`. A campaign of another company, or of the platform, does not exist for a company (404).
 */
final readonly class CampaignAccess
{
    public function __construct(
        private CompanyDirectory $companies,
        private CampaignRepository $campaigns,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     */
    public function scope(Actor $actor): CampaignScope
    {
        if ($actor->kind === ActorKind::Admin && $actor->id !== null) {
            return new CampaignScope(null, true);
        }

        if ($actor->kind !== ActorKind::CompanyMember || $actor->membership === null || $actor->id === null) {
            throw new AccessDenied('Only a company member can manage campaigns.', 'not-a-company-member');
        }

        if (! $this->companies->isActive($actor->membership->companyId)) {
            throw new AccessDenied('This company is suspended.', 'company-suspended');
        }

        $actor->membership->require(CompanyPermission::CampaignsManage);

        return new CampaignScope($actor->membership->companyId, false);
    }

    /**
     * @throws EntityNotFound when the campaign does not exist or is not in the scope
     */
    public function campaign(CampaignScope $scope, int $campaignId, bool $forUpdate = false): Campaign
    {
        $campaign = $this->campaigns->find($campaignId, $forUpdate);

        if ($campaign === null || (! $scope->isAdmin && $campaign->companyId !== $scope->companyId)) {
            throw EntityNotFound::of('Campaign', $campaignId);
        }

        return $campaign;
    }
}
