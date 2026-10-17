<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\Scorecard;
use Src\Recruitment\Domain\ScorecardRepository;
use Src\Recruitment\Domain\ScorecardRevision;
use Src\Shared\Application\Actor;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/** The edit history of a sent scorecard, newest first, under the same visibility rule as the scorecards themselves. */
final readonly class ListScorecardRevisions
{
    public function __construct(
        private TeamInterviewLookup $lookup,
        private ScorecardAccess $access,
        private ScorecardRepository $scorecards,
    ) {}

    /**
     * @return list<ScorecardRevision>
     *
     * @throws AccessDenied `not-a-company-member`, `company-suspended` or `scorecards-locked`
     * @throws EntityNotFound
     */
    public function execute(Actor $actor, int $interviewId, int $scorecardId): array
    {
        $team = $this->lookup->find($actor, $interviewId, panelOpens: true);
        $this->access->assertMayReadSent($team);
        $scorecard = $this->scorecards->findById($scorecardId);

        if (! $scorecard instanceof Scorecard || $scorecard->interviewId !== $interviewId || ! $scorecard->isSubmitted()) {
            throw EntityNotFound::of('Scorecard', $scorecardId);
        }

        return $this->scorecards->revisions($scorecardId);
    }
}
