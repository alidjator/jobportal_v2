<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationRepository;
use Src\Recruitment\Domain\OfferRepository;
use Src\Recruitment\Domain\OfferStatus;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\FileStorage;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The letter PDF of the offer the job seeker can answer or has accepted (the newest offer that is pending, or accepted). Another person's application, an
 * application with no such offer and an offer without a letter all answer as not found.
 */
final readonly class GetMyOfferLetter
{
    public function __construct(
        private ApplicantGuard $guard,
        private ApplicationRepository $applications,
        private OfferRepository $offers,
        private FileStorage $files,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $applicationId): ApplicationFile
    {
        $this->applications->findOfApplicant($this->guard->userId($actor), $applicationId) ?? throw EntityNotFound::of('Application', $applicationId);
        $now = $this->clock->now();

        foreach ($this->offers->forApplication($applicationId) as $offer) {
            if (! in_array($offer->statusAt($now), [OfferStatus::Pending, OfferStatus::Accepted], true)) {
                continue;
            }

            $contents = $offer->letter === null ? null : $this->files->get($offer->letter->path);

            if ($contents === null) {
                break;
            }

            return new ApplicationFile($contents, 'offer-letter-'.$offer->id);
        }

        throw EntityNotFound::of('Offer letter', $applicationId);
    }
}
