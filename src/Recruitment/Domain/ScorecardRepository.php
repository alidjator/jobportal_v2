<?php

namespace Src\Recruitment\Domain;

interface ScorecardRepository
{
    /** The member's scorecard of the interview with its answers, draft or sent. */
    public function find(int $interviewId, int $memberId): ?Scorecard;

    public function findById(int $scorecardId): ?Scorecard;

    /**
     * The sent scorecards of the interview with their answers, in the order they were sent.
     *
     * @return list<Scorecard>
     */
    public function sent(int $interviewId): array;

    /** @return list<int> the members who sent their scorecard */
    public function sentBy(int $interviewId): array;

    /** Inserts a new scorecard or saves the changed one, answers included. */
    public function save(Scorecard $scorecard): Scorecard;

    public function addRevision(ScorecardRevision $revision): void;

    /**
     * The edits of a scorecard, newest first.
     *
     * @return list<ScorecardRevision>
     */
    public function revisions(int $scorecardId): array;

    /**
     * Deletes the drafts of the given members (a sent scorecard stays).
     *
     * @param  list<int>  $memberIds
     */
    public function deleteDrafts(int $interviewId, array $memberIds): void;
}
