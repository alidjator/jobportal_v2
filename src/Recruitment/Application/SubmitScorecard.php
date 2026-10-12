<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Scorecard;
use Src\Recruitment\Domain\ScorecardRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\Clock;
use Src\Shared\Application\TransactionManager;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;
use Src\Shared\Domain\Exceptions\StateConflict;

/** Sends the caller's draft scorecard, which needs its recommendation. Sending a scorecard that was sent already changes nothing. */
final readonly class SubmitScorecard
{
    public function __construct(
        private TeamInterviewLookup $lookup,
        private ScorecardAccess $access,
        private ScorecardRepository $scorecards,
        private ScorecardViews $views,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `not-on-panel`
     * @throws EntityNotFound
     * @throws StateConflict `application-final` or `scoring-not-open`
     * @throws BusinessRuleViolation `recommendation-required`
     */
    public function execute(Actor $actor, int $interviewId): ScorecardView
    {
        $saved = $this->transactions->run(function () use ($actor, $interviewId): Scorecard {
            $team = $this->lookup->find($actor, $interviewId, forUpdate: true);
            $this->access->assertOnPanel($team);
            $now = $this->clock->now();
            $this->access->assertOpen($team, $now);

            $current = $this->scorecards->find($interviewId, $team->member->userId) ?? Scorecard::blank($interviewId, $team->member->userId);

            return $current->isSubmitted() ? $current : $this->scorecards->save($current->submit($now));
        });

        return $this->views->one($saved);
    }
}
