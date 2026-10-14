<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Events\OfferChange;
use Src\Recruitment\Domain\Events\OfferChanged;
use Src\Recruitment\Domain\Offer;
use Src\Recruitment\Domain\OfferRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** The team takes back the pending offer of an application. The application stays in its stage; the job seeker is told. */
final readonly class WithdrawOffer
{
    public function __construct(
        private TeamApplicationLookup $lookup,
        private OfferRepository $offers,
        private OfferViews $views,
        private TransactionManager $transactions,
        private EventDispatcher $events,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     * @throws StateConflict `no-active-offer`
     */
    public function execute(Actor $actor, int $applicationId): OfferView
    {
        [$saved, $event] = $this->transactions->run(function () use ($actor, $applicationId): array {
            $team = $this->lookup->find($actor, $applicationId, forUpdate: true, permission: CompanyPermission::OffersManage);
            $offer = $this->offers->active($applicationId, forUpdate: true) ?? throw new StateConflict('This application has no pending offer.', 'no-active-offer');
            $saved = $offer->withdraw();
            $this->offers->update($saved);

            return [$saved, OfferChanged::of(OfferChange::Withdrawn, $team->application, $saved, $this->clock->now())];
        });

        $this->events->dispatch([$event]);

        return $this->views->one($saved);
    }
}
