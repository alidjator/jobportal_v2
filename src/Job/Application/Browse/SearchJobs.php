<?php

namespace Src\Job\Application\Browse;

use Src\Job\Application\Matching\ScoreJobsForSeeker;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * The public job search, for anyone. It limits each client address, refuses a minimum salary without its period and a position deeper than 10,000, looks up
 * the query it runs (see `JobSearchQueryBuilder`), and asks the reader for the live jobs of companies the public may see. The rows come back with the
 * company card and names in the language of the request. For a job seeker with a complete profile (`$viewerId`) every row also carries how well they match it.
 */
final readonly class SearchJobs
{
    public const int MAX_POSITION = 10000;

    public function __construct(
        private SearchThrottle $throttle,
        private JobSearchReader $reader,
        private JobSearchQueryBuilder $queries,
        private PublicJobListItems $items,
        private ScoreJobsForSeeker $scores,
    ) {}

    /**
     * @return Page<PublicJobListItem>
     *
     * @throws TooManyAttempts
     * @throws BusinessRuleViolation `salary-period-required` or `page-out-of-range`
     */
    public function execute(JobSearchCriteria $criteria, PageRequest $page, ?string $ipAddress, string $language, ?int $viewerId = null): Page
    {
        $this->throttle->hit($ipAddress);

        if ($criteria->salaryMin !== null && $criteria->salaryPeriodId === null) {
            throw new BusinessRuleViolation('A minimum salary needs the salary period it is meant in.', 'salary-period-required');
        }

        if ($page->page * $page->perPage > self::MAX_POSITION) {
            throw new BusinessRuleViolation('The search shows at most the first '.self::MAX_POSITION.' results; narrow it down instead.', 'page-out-of-range');
        }

        $hits = $this->reader->page($this->queries->build($criteria), $page);

        $items = $this->items->of($hits->items, $language);
        $scores = $viewerId === null ? null : $this->scores->forJobs($viewerId, array_map(static fn (PublicJobListItem $item): int => $item->id, $items));

        if ($scores !== null) {
            $items = array_map(static fn (PublicJobListItem $item): PublicJobListItem => $item->withMatch($scores[$item->id] ?? null), $items);
        }

        return new Page($items, $hits->total, $hits->request);
    }
}
