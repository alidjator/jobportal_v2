<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Events\OfferChange;
use Src\Recruitment\Domain\OfferRepository;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\EventDispatcher;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Sends the offer of an application that is in an offer stage, or replaces the pending one with a new offer. The job seeker is told. */
final readonly class SendOffer
{
    public function __construct(
        private TeamApplicationLookup $lookup,
        private OfferPlacer $placer,
        private OfferRepository $offers,
        private OfferViews $views,
        private JobDirectory $jobs,
        private TransactionManager $transactions,
        private EventDispatcher $events,
    ) {}

    /**
     * @return array{view: OfferView, replaced: bool}
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     * @throws StateConflict `job-taken-down` or `not-in-offer-stage`
     * @throws BusinessRuleViolation `invalid-offer`
     */
    public function execute(Actor $actor, int $applicationId, OfferInput $input): array
    {
        $event = $this->transactions->run(function () use ($actor, $applicationId, $input) {
            $team = $this->lookup->find($actor, $applicationId, forUpdate: true, permission: CompanyPermission::OffersManage);

            if ($this->jobs->isTakenDown($team->application->jobId)) {
                throw new StateConflict('This job was taken down, so its applications can no longer be processed.', 'job-taken-down');
            }

            if ($team->application->stageType !== StageType::Offer) {
                throw new StateConflict('Move the application to an offer stage before sending an offer.', 'not-in-offer-stage');
            }

            return $this->placer->place($team->member, $team->application, $input);
        });

        $this->events->dispatch([$event]);

        return [
            'view' => $this->views->one($this->offers->findById($event->offerId) ?? throw EntityNotFound::of('Offer', $event->offerId)),
            'replaced' => $event->change === OfferChange::Revised,
        ];
    }
}
