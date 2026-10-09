<?php

namespace Src\Campaign\Application;

use Src\Campaign\Domain\CampaignRepository;
use Src\Campaign\Domain\Events\CampaignChanged;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Deletes a campaign nobody registered with. A campaign with registrations is switched off instead, so its figures and its code stay. */
final readonly class DeleteCampaign
{
    public function __construct(
        private CampaignAccess $access,
        private CampaignRepository $campaigns,
        private CampaignStatistics $statistics,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-admin-required`
     * @throws EntityNotFound when the campaign is not in the actor's scope
     * @throws StateConflict `campaign-has-registrations`
     */
    public function execute(Actor $actor, int $campaignId, ?string $ipAddress, ?string $userAgent): void
    {
        $scope = $this->access->scope($actor);
        $now = $this->clock->now();

        $companyId = $this->transactions->run(function () use ($scope, $campaignId): ?int {
            $campaign = $this->access->campaign($scope, $campaignId, true);

            if ($this->statistics->registrations($campaign->code) > 0) {
                throw new StateConflict('Someone registered with this campaign; switch it off instead.', 'campaign-has-registrations');
            }

            $this->campaigns->delete($campaignId);

            return $campaign->companyId;
        });

        $this->events->dispatch([new CampaignChanged($campaignId, $companyId, CampaignChanged::DELETED, $scope->isAdmin ? 'admin' : 'user', (int) $actor->id, $ipAddress, $userAgent, $now)]);
    }
}
