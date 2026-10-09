<?php

namespace Src\Campaign\Application;

use Src\Campaign\Domain\CampaignQuery;
use Src\Campaign\Domain\CampaignRepository;
use Src\Campaign\Domain\CampaignStatus;
use Src\Campaign\Domain\CampaignType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\AccessDenied;

/** The campaigns in the actor's scope, newest first. A company admin always sees their own company's only; a platform admin may narrow to one company. */
final readonly class ListCampaigns
{
    public function __construct(
        private CampaignAccess $access,
        private CampaignRepository $campaigns,
        private CampaignViews $views,
        private Clock $clock,
    ) {}

    /**
     * @return Page<CampaignView>
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-admin-required`
     */
    public function execute(Actor $actor, ?int $companyId, ?string $search, ?CampaignType $type, ?CampaignStatus $status, PageRequest $request): Page
    {
        $scope = $this->access->scope($actor);
        $page = $this->campaigns->page(
            new CampaignQuery($scope->isAdmin ? $companyId : $scope->companyId, $search, $type, $status, JakartaToday::of($this->clock->now())),
            $request,
        );

        return new Page($this->views->of($page->items), $page->total, $page->request);
    }
}
