<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Scorecard;
use Src\Recruitment\Domain\ScorecardRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The caller's own scorecard of an interview: the draft or the sent one, or an empty draft (not saved) when none was started. */
final readonly class GetMyScorecard
{
    public function __construct(
        private TeamInterviewLookup $lookup,
        private ScorecardAccess $access,
        private ScorecardRepository $scorecards,
        private ScorecardViews $views,
    ) {}

    /**
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `not-on-panel`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $interviewId): ScorecardView
    {
        $team = $this->lookup->find($actor, $interviewId, panelOpens: true);
        $this->access->assertOnPanel($team);

        return $this->views->one($this->scorecards->find($interviewId, $team->member->userId) ?? Scorecard::blank($interviewId, $team->member->userId));
    }
}
