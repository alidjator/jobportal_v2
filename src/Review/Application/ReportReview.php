<?php

namespace Src\Review\Application;

use Src\Review\Domain\Report;
use Src\Review\Domain\ReporterKind;
use Src\Review\Domain\ReportReason;
use Src\Review\Domain\ReportRepository;
use Src\Review\Domain\ReviewRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\Clock;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** A job seeker, or a company admin for their own company's page, reports a published review for the admins to look at. Reports are never shown to the author or the public. */
final readonly class ReportReview
{
    public function __construct(
        private ReviewGate $gate,
        private ReviewRepository $reviews,
        private ReportRepository $reports,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `own-review`, `job-seeker-only`, `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound the review is not published (or not the company's)
     * @throws StateConflict `already-reported`
     */
    public function execute(Actor $actor, int $reviewId, ReportReason $reason, ?string $note): void
    {
        $company = $actor->kind === ActorKind::CompanyMember;
        $reporterId = $company ? $this->gate->memberCompanyId($actor, true) : $this->gate->jobSeekerId($actor);
        $review = $this->reviews->find($reviewId);

        if ($review === null || ! $review->isPublic() || ($company && $review->companyId !== $reporterId)) {
            throw EntityNotFound::of('Review', $reviewId);
        }

        if (! $company && $review->userId === $reporterId) {
            throw new AccessDenied('You cannot report your own review.', 'own-review');
        }

        $kind = $company ? ReporterKind::Company : ReporterKind::User;

        if ($this->reports->hasOpen($reviewId, $kind, $reporterId)) {
            throw new StateConflict('You already reported this review.', 'already-reported');
        }

        $this->reports->add(Report::open($reviewId, $kind, $reporterId, $reason, $note, $this->clock->now()));
    }
}
