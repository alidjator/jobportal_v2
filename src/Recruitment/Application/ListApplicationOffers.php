<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\OfferRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** Every offer of an application, newest first (the pending one, then the ones withdrawn, answered or expired), for the members who may see its job. */
final readonly class ListApplicationOffers
{
    public function __construct(private TeamApplicationLookup $lookup, private OfferRepository $offers, private OfferViews $views) {}

    /**
     * @return list<OfferView>
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $applicationId): array
    {
        $this->lookup->find($actor, $applicationId);

        return $this->views->of($this->offers->forApplication($applicationId));
    }
}
