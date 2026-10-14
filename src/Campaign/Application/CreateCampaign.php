<?php

namespace Src\Campaign\Application;

use Src\Campaign\Domain\Campaign;
use Src\Campaign\Domain\CampaignRepository;
use Src\Campaign\Domain\Events\CampaignChanged;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A company admin creates a campaign of their company; a platform admin creates one for any company or, with no company, for the platform. The code is kept in lower
 * case, is unique across every campaign and never changes.
 */
final readonly class CreateCampaign
{
    public function __construct(
        private CampaignAccess $access,
        private CampaignRepository $campaigns,
        private CompanyDirectory $companies,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @param  int|null  $companyId  the company of the campaign, used for a platform admin only (null: a platform campaign); a company admin always creates for their own
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound when a platform admin names a company that does not exist
     * @throws BusinessRuleViolation `campaign-period-invalid`
     * @throws StateConflict `campaign-code-taken`
     */
    public function execute(Actor $actor, string $code, CampaignInput $input, ?int $companyId, ?string $ipAddress, ?string $userAgent): Campaign
    {
        $scope = $this->access->scope($actor);
        $companyId = $scope->isAdmin ? $companyId : $scope->companyId;

        if ($companyId !== null && ! $this->companies->exists($companyId)) {
            throw EntityNotFound::of('Company', $companyId);
        }

        $now = $this->clock->now();
        $code = mb_strtolower(trim($code));
        $campaign = new Campaign(
            $companyId, $code, $input->name, $input->type, $input->startsOn, $input->endsOn, $input->active,
            $scope->isAdmin ? null : $actor->id, $scope->isAdmin ? $actor->id : null, $now, $now,
        );

        $saved = $this->transactions->run(function () use ($campaign, $code): Campaign {
            if ($this->campaigns->findByCode($code) !== null) {
                throw new StateConflict('This campaign code is already used.', 'campaign-code-taken');
            }

            return $this->campaigns->add($campaign);
        });

        $this->events->dispatch([new CampaignChanged((int) $saved->id, $saved->companyId, CampaignChanged::CREATED, $scope->isAdmin ? 'admin' : 'user', (int) $actor->id, $ipAddress, $userAgent, $now)]);

        return $saved;
    }
}
