<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ScorecardRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The sent scorecards of an interview, for the members who may see them (a panel member who has not sent their own does not). */
final readonly class ListSentScorecards
{
    public function __construct(
        private TeamInterviewLookup $lookup,
        private ScorecardAccess $access,
        private ScorecardRepository $scorecards,
        private ScorecardViews $views,
    ) {}

    /**
     * @return list<ScorecardView>
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `scorecards-locked`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $interviewId): array
    {
        $team = $this->lookup->find($actor, $interviewId);
        $this->access->assertMayReadSent($team);

        return $this->views->of($this->scorecards->sent($interviewId));
    }
}
