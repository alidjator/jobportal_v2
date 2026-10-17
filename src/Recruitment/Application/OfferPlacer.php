<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Application;
use Src\Recruitment\Domain\Events\OfferChange;
use Src\Recruitment\Domain\Events\OfferChanged;
use Src\Recruitment\Domain\Offer;
use Src\Recruitment\Domain\OfferRepository;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CandidateMessageRules;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * Puts an offer on an application: withdraws the offer that was pending (a new offer replaces it) and saves the new one. It runs inside the caller's
 * transaction, with the application row already locked.
 */
final readonly class OfferPlacer
{
    public function __construct(
        private OfferRepository $offers,
        private JobDirectory $jobs,
        private CandidateMessageRules $messageRules,
        private Clock $clock,
    ) {}

    /** @throws BusinessRuleViolation `invalid-offer` or `message-link-not-allowed` */
    public function place(RecruitmentMember $member, Application $application, OfferInput $input): OfferChanged
    {
        $now = $this->clock->now();
        $applicationId = (int) $application->id;
        $this->messageRules->assertAllowed($application->companyId, $input->note, 'offer.note');

        $offer = Offer::make(
            $applicationId,
            $input->position ?? $this->jobs->titles([$application->jobId])[$application->jobId] ?? '',
            $input->salary,
            $input->startDate,
            $input->note,
            $input->expiresAt,
            $member->userId,
            $now,
        );

        $previous = $this->offers->active($applicationId, forUpdate: true);

        if ($previous instanceof Offer) {
            $this->offers->update($previous->withdraw());
        }

        $saved = $this->offers->add($offer);

        return OfferChanged::of($previous instanceof Offer ? OfferChange::Revised : OfferChange::Sent, $application, $saved, $now);
    }
}
