<?php

namespace Src\Job\Application\Matching;

use Src\Job\Application\Browse\JobSearchHit;
use Src\Job\Application\Browse\PublicJobListItems;
use Src\Job\Domain\SavedJobRepository;
use Src\Shared\Application\Actor;
use Src\Shared\Application\ActorKind;
use Src\Shared\Application\JobInvitation\InvitedCandidates;
use Src\Shared\Application\Matching\CandidateSignalSource;
use Src\Shared\Application\Matching\MatchScore;
use Src\Shared\Application\Matching\MatchScoring;
use Src\Shared\Application\Recruitment\ApplicantRelations;
use Src\Shared\Domain\Exceptions\AccessDenied;

/**
 * The live jobs that fit a job seeker best (J8): the newest 300 that are remote or in their province, scored, the 20 best of 40% or more. Jobs they applied to or
 * were invited to are left out. A job seeker without a complete profile gets nothing.
 */
final readonly class RecommendJobs
{
    public const int LIMIT = 20;

    private const int POOL = 300;

    public function __construct(
        private CandidateSignalSource $candidates,
        private JobPool $pool,
        private JobSignalReader $signals,
        private MatchScoring $scoring,
        private ApplicantRelations $applications,
        private InvitedCandidates $invitations,
        private PublicJobListItems $items,
        private SavedJobRepository $saved,
    ) {}

    /**
     * @return list<RecommendedJob> the best fit first
     *
     * @throws AccessDenied `job-seeker-only`
     */
    public function execute(Actor $actor, string $language): array
    {
        if ($actor->kind !== ActorKind::JobSeeker || $actor->id === null) {
            throw new AccessDenied('Only a job seeker gets job recommendations.', 'job-seeker-only');
        }

        $candidate = $this->candidates->forUsers([$actor->id])[$actor->id] ?? null;

        if ($candidate === null) {
            return [];
        }

        $hits = $this->pool->hits($candidate->provinceId, self::POOL);
        $ids = array_map(static fn (JobSearchHit $hit): int => $hit->id, $hits);
        $skip = [...$this->applications->appliedJobIds($actor->id, $ids), ...$this->invitations->jobIdsOf($actor->id, $ids)];
        $signals = $this->signals->forJobs($ids);

        /** @var list<array{hit: JobSearchHit, score: MatchScore}> $best */
        $best = [];

        foreach ($hits as $hit) {
            if (! isset($signals[$hit->id]) || in_array($hit->id, $skip, true)) {
                continue;
            }

            $score = $this->scoring->score($candidate, $signals[$hit->id]);

            if ($score->isRecommended()) {
                $best[] = ['hit' => $hit, 'score' => $score];
            }
        }

        // The pool is the newest first and the sort is stable, so equal scores keep that order.
        usort($best, static fn (array $a, array $b): int => $b['score']->percent <=> $a['score']->percent);
        $best = array_slice($best, 0, self::LIMIT);

        $items = [];
        $savedIds = $this->saved->savedAmong($actor->id, array_map(static fn (array $entry): int => $entry['hit']->id, $best));

        foreach ($this->items->of(array_map(static fn (array $entry): JobSearchHit => $entry['hit'], $best), $language) as $item) {
            $items[$item->id] = $item->withSaved(in_array($item->id, $savedIds, true));
        }

        $recommended = [];

        foreach ($best as $entry) {
            // A job whose company became hidden between the two reads is left out.
            if (isset($items[$entry['hit']->id])) {
                $recommended[] = new RecommendedJob($items[$entry['hit']->id]->withMatch($entry['score']), $entry['score']);
            }
        }

        return $recommended;
    }
}
