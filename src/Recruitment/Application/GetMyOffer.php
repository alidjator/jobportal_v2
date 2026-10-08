<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\OfferRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The pending offer of one of the applicant's own applications, or null when it has none. Another person's application does not exist for the caller. */
final readonly class GetMyOffer
{
    public function __construct(
        private ApplicantGuard $guard,
        private ApplicationRepository $applications,
        private OfferRepository $offers,
        private OfferViews $views,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $applicationId): ?OfferView
    {
        $this->applications->findOfApplicant($this->guard->userId($actor), $applicationId) ?? throw EntityNotFound::of('Application', $applicationId);
        $offer = $this->offers->active($applicationId);

        return $offer === null ? null : $this->views->one($offer);
    }
}
