<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationRatingRepository;
use Src\Recruitment\Domain\StageType;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\Company\CompanyPermission;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/**
 * A member rates an application from 1 to 5 stars, changes the rating or withdraws it. One rating per member. A rejected, withdrawn or hired application is
 * final, so its ratings are locked; the application row is locked first so a rating that races with a rejection meets its result.
 */
final readonly class RateApplication
{
    public function __construct(
        private TeamApplicationLookup $lookup,
        private ApplicationRatingRepository $ratings,
        private ApplicationRatings $views,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     * @throws StateConflict `application-final`
     * @throws BusinessRuleViolation `invalid-rating`
     */
    public function rate(Actor $actor, int $applicationId, int $stars): ApplicationRatingView
    {
        if ($stars < 1 || $stars > 5) {
            throw new BusinessRuleViolation('Rate from 1 to 5 stars.', 'invalid-rating', extensions: ['errors' => ['stars' => ['Rate from 1 to 5 stars.']]]);
        }

        return $this->change($actor, $applicationId, fn (int $memberId) => $this->ratings->set($applicationId, $memberId, $stars, $this->clock->now()));
    }

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `company-permission-required`
     * @throws EntityNotFound
     * @throws StateConflict `application-final`
     */
    public function withdraw(Actor $actor, int $applicationId): ApplicationRatingView
    {
        return $this->change($actor, $applicationId, fn (int $memberId) => $this->ratings->remove($applicationId, $memberId));
    }

    /** @param  callable(int): void  $write */
    private function change(Actor $actor, int $applicationId, callable $write): ApplicationRatingView
    {
        return $this->transactions->run(function () use ($actor, $applicationId, $write): ApplicationRatingView {
            $team = $this->lookup->find($actor, $applicationId, forUpdate: true, permission: CompanyPermission::ApplicationsReview);

            $type = $team->application->stageType;

            if ($type->isTerminal() || $type === StageType::Hired) {
                throw new StateConflict('This application is final, so its ratings can no longer change.', 'application-final');
            }

            $write($team->member->userId);

            return $this->views->view($applicationId, $team->member->userId);
        });
    }
}
