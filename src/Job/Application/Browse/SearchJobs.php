<?php

namespace Src\Job\Application\Browse;

use Src\Job\Application\Matching\ScoreJobsForSeeker;
use Src\Job\Domain\SavedJobRepository;
use Src\Shared\Application\Matching\MatchScore;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * The public job search, for anyone. It limits each client address, refuses a minimum salary without its period and a position deeper than 10,000, looks up
 * the query it runs (see `JobSearchQueryBuilder`), and asks the reader for the live jobs of companies the public may see. The rows come back with the
 * company card and names in the language of the request. For a job seeker (`$viewerId`) every row also says whether they saved the job, and, with a complete
 * profile, how well they match it. `sort=match` ranks the first 500 results by that percentage.
 */
final readonly class SearchJobs
{
    public const int MAX_POSITION = 10000;

    /** How many jobs the best-match order scores and ranks. */
    private const int MATCH_POOL = 500;

    public function __construct(
        private SearchThrottle $throttle,
        private JobSearchReader $reader,
        private JobSearchQueryBuilder $queries,
        private PublicJobListItems $items,
        private ScoreJobsForSeeker $scores,
        private SavedJobRepository $saved,
    ) {}

    /**
     * @throws TooManyAttempts
     * @throws BusinessRuleViolation `salary-period-required` or `page-out-of-range`
     */
    public function execute(JobSearchCriteria $criteria, PageRequest $page, ?string $ipAddress, string $language, ?int $viewerId = null): SearchJobsResult
    {
        $this->throttle->hit($ipAddress);

        if ($criteria->salaryMin !== null && $criteria->salaryPeriodId === null) {
            throw new BusinessRuleViolation('A minimum salary needs the salary period it is meant in.', 'salary-period-required');
        }

        if ($page->page * $page->perPage > self::MAX_POSITION) {
            throw new BusinessRuleViolation('The search shows at most the first :max results; narrow it down instead.', 'page-out-of-range', parameters: ['max' => self::MAX_POSITION]);
        }

        $query = $this->queries->build($criteria);
        $sort = $criteria->sort;

        if ($sort === JobSearchSort::Match) {
            // Needs a signed-in job seeker with a complete profile; otherwise the order falls back like any other.
            if ($viewerId !== null && $this->scores->forJobs($viewerId, []) !== null) {
                return $this->byMatch($query, $page, $language, $viewerId);
            }

            $sort = JobSearchSort::Relevance;
        }

        if ($sort === JobSearchSort::Relevance && $query->keyword === null) {
            $sort = JobSearchSort::PublishedAtDescending;
        }

        if ($sort !== $criteria->sort) {
            $query = $this->queries->build($criteria->withSort($sort));
        }

        $hits = $this->reader->page($query, $page);
        $items = $this->items->of($hits->items, $language);
        $scores = $viewerId === null ? null : $this->scores->forJobs($viewerId, array_map(static fn (PublicJobListItem $item): int => $item->id, $items));

        return new SearchJobsResult(new Page($this->decorate($items, $scores, $viewerId), $hits->total, $hits->request), $sort);
    }

    /** The best `MATCH_POOL` jobs of the filtered result (by relevance, or newest without a keyword) ranked by percentage, then newest; a job without a percentage comes last. */
    private function byMatch(JobSearchQuery $query, PageRequest $page, string $language, int $viewerId): SearchJobsResult
    {
        $hits = $this->reader->topHits($query, self::MATCH_POOL);
        $scores = $this->scores->forJobs($viewerId, array_map(static fn (JobSearchHit $hit): int => $hit->id, $hits)) ?? [];

        usort($hits, static fn (JobSearchHit $a, JobSearchHit $b): int => [$scores[$b->id]->percent ?? -1, $b->publishedAt?->getTimestamp() ?? 0, $b->id]
            <=> [$scores[$a->id]->percent ?? -1, $a->publishedAt?->getTimestamp() ?? 0, $a->id]);

        $items = $this->items->of(array_slice($hits, $page->offset(), $page->perPage), $language);

        return new SearchJobsResult(new Page($this->decorate($items, $scores, $viewerId), count($hits), $page), JobSearchSort::Match);
    }

    /**
     * @param  list<PublicJobListItem>  $items
     * @param  array<int, MatchScore>|null  $scores
     * @return list<PublicJobListItem>
     */
    private function decorate(array $items, ?array $scores, ?int $viewerId): array
    {
        if ($scores !== null) {
            $items = array_map(static fn (PublicJobListItem $item): PublicJobListItem => $item->withMatch($scores[$item->id] ?? null), $items);
        }

        if ($viewerId !== null) {
            $saved = $this->saved->savedAmong($viewerId, array_map(static fn (PublicJobListItem $item): int => $item->id, $items));
            $items = array_map(static fn (PublicJobListItem $item): PublicJobListItem => $item->withSaved(in_array($item->id, $saved, true)), $items);
        }

        return $items;
    }
}
