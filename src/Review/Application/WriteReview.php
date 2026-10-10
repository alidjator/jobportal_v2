<?php

namespace Src\Review\Application;

use Src\Review\Domain\Review;
use Src\Review\Domain\ReviewContent;
use Src\Review\Domain\ReviewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Recruitment\ApplicantRelations;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A job seeker writes the one review of a company they applied to (whatever happened to the application). It waits for an admin before anyone sees it. */
final readonly class WriteReview
{
    public function __construct(
        private ReviewGate $gate,
        private ApplicantRelations $applicants,
        private ReviewRepository $reviews,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `job-seeker-only` or `review-requires-application`
     * @throws EntityNotFound the company does not exist or is hidden
     * @throws StateConflict `already-reviewed`
     */
    public function execute(Actor $actor, string $companySlug, ReviewContent $content): Review
    {
        $userId = $this->gate->jobSeekerId($actor);
        $company = $this->gate->publicCompany($companySlug);

        if (! $this->applicants->hasApplied($company->id, $userId)) {
            throw new AccessDenied('Only a job seeker who applied to this company can review it.', 'review-requires-application');
        }

        if ($this->reviews->findOfUser($company->id, $userId) !== null) {
            throw new StateConflict('You already reviewed this company.', 'already-reviewed');
        }

        return $this->reviews->add(Review::write($company->id, $userId, $content, $this->clock->now()));
    }
}
