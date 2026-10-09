<?php

namespace Src\JobSeeker\Application\Candidates;

use Src\Shared\Application\Actor;
use Src\Shared\Application\Job\JobDirectory;
use Src\Shared\Application\JobInvitation\InvitedCandidates;
use Src\Shared\Application\Matching\CandidateSignalSource;
use Src\Shared\Application\Matching\JobSignalSource;
use Src\Shared\Application\Matching\MatchScore;
use Src\Shared\Application\Matching\MatchScoring;
use Src\Shared\Application\Recruitment\ApplicantRelations;
use Src\Shared\Domain\Exceptions\AccessDenied;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\EntityNotFound;

/**
 * The candidates that fit a live job best (R12): the most recently updated candidates a company may find, in the job's province (anywhere for a remote job),
 * at most 500, scored with the engine of the job recommendations; the 20 best of 40% or more. Candidates who applied to the job are left out; an invited one stays
 * and is marked. Nothing is logged: the list shows the same as the search list, and no contact data.
 */
final readonly class RecommendCandidates
{
    public const int LIMIT = 20;

    private const int POOL = 500;

    public function __construct(
        private CandidateAccess $access,
        private JobDirectory $jobs,
        private JobSignalSource $jobSignals,
        private CandidateReader $candidates,
        private CandidateSignalSource $candidateSignals,
        private MatchScoring $scoring,
        private ApplicantRelations $applications,
        private InvitedCandidates $invitations,
        private CandidateListItems $items,
    ) {}

    /**
     * @return list<RecommendedCandidate> the best fit first
     *
     * @throws AccessDenied `not-a-company-member` or `company-suspended`
     * @throws EntityNotFound the job is missing, foreign or not theirs
     * @throws BusinessRuleViolation `job-not-live`
     */
    public function execute(Actor $actor, int $jobId, string $language): array
    {
        $viewer = $this->access->viewer($actor);
        $this->jobs->visibleToMember($jobId, $viewer->companyId, $viewer->memberId, $viewer->isCompanyAdmin) ?? throw EntityNotFound::of('Job', $jobId);

        if ($this->jobs->publicCompanyId($jobId) !== $viewer->companyId) {
            throw new BusinessRuleViolation('Candidates are only recommended for a job that is live.', 'job-not-live');
        }

        $job = $this->jobSignals->forJobs([$jobId])[$jobId] ?? throw EntityNotFound::of('Job', $jobId);
        $rows = $this->candidates->pool($job->remote ? null : $job->provinceId, self::POOL);
        $userIds = array_map(static fn (CandidateRow $row): int => $row->userId, $rows);
        $signals = $this->candidateSignals->forUsers($userIds);
        $applied = $this->applications->appliedUserIds($jobId, $userIds);

        /** @var list<array{row: CandidateRow, score: MatchScore}> $best */
        $best = [];

        foreach ($rows as $row) {
            if (! isset($signals[$row->userId]) || in_array($row->userId, $applied, true)) {
                continue;
            }

            $score = $this->scoring->score($signals[$row->userId], $job);

            if ($score->isRecommended()) {
                $best[] = ['row' => $row, 'score' => $score];
            }
        }

        // The pool is the most recently updated first and the sort is stable, so equal scores keep that order.
        usort($best, static fn (array $a, array $b): int => $b['score']->percent <=> $a['score']->percent);
        $best = array_slice($best, 0, self::LIMIT);

        $invited = $this->invitations->userIdsOf($jobId, array_map(static fn (array $entry): int => $entry['row']->userId, $best));
        $items = $this->items->of(array_map(static fn (array $entry): CandidateRow => $entry['row'], $best), $language);

        return array_map(
            static fn (array $entry, CandidateListItem $item): RecommendedCandidate => new RecommendedCandidate($item, $entry['score'], in_array($entry['row']->userId, $invited, true)),
            $best,
            $items,
        );
    }
}
