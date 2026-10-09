<?php

namespace Src\Campaign\Application;

use Src\Campaign\Domain\Campaign;
use Src\Campaign\Domain\CampaignRepository;
use Src\Campaign\Domain\Events\CampaignChanged;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Changes the name, type, period and on/off switch of a campaign in the actor's scope. The code and the owner never change. */
final readonly class UpdateCampaign
{
    public function __construct(
        private CampaignAccess $access,
        private CampaignRepository $campaigns,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-admin-required`
     * @throws EntityNotFound when the campaign is not in the actor's scope
     * @throws BusinessRuleViolation `campaign-period-invalid`
     */
    public function execute(Actor $actor, int $campaignId, CampaignInput $input, ?string $ipAddress, ?string $userAgent): Campaign
    {
        $scope = $this->access->scope($actor);
        $now = $this->clock->now();

        $updated = $this->transactions->run(function () use ($scope, $campaignId, $input, $now): Campaign {
            $campaign = $this->access->campaign($scope, $campaignId, true);
            $changed = $campaign->changed($input->name, $input->type, $input->startsOn, $input->endsOn, $input->active, $now);
            $this->campaigns->update($changed);

            return $changed;
        });

        $this->events->dispatch([new CampaignChanged($campaignId, $updated->companyId, CampaignChanged::UPDATED, $scope->isAdmin ? 'admin' : 'user', (int) $actor->id, $ipAddress, $userAgent, $now)]);

        return $updated;
    }
}
