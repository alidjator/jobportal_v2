<?php

namespace Src\Job\Application\Browse;

use Src\Shared\Application\Company\PublicCompanies;
use Src\Shared\Application\Pagination\Page;
use Src\Shared\Application\Pagination\PageRequest;
use Src\Shared\Application\References\LocationReferences;
use Src\Shared\Application\References\ReferenceValues;
use Src\Shared\Domain\Exceptions\BusinessRuleViolation;
use Src\Shared\Domain\Exceptions\TooManyAttempts;

/**
 * The public job search, for anyone. It limits each client address, refuses a minimum salary without its period and a position deeper than 10,000, looks up
 * the query it runs (see `JobSearchQueryBuilder`), and asks the reader for the live jobs of companies the public may see. The rows come back with the
 * company card and names in the language of the request.
 */
final readonly class SearchJobs
{
    public const int MAX_POSITION = 10000;

    public function __construct(
        private SearchThrottle $throttle,
        private JobSearchReader $reader,
        private JobSearchQueryBuilder $queries,
        private PublicCompanies $publicCompanies,
        private ReferenceValues $references,
        private LocationReferences $locations,
    ) {}

    /**
     * @return Page<PublicJobListItem>
     *
     * @throws TooManyAttempts
     * @throws BusinessRuleViolation `salary-period-required` or `page-out-of-range`
     */
    public function execute(JobSearchCriteria $criteria, PageRequest $page, ?string $ipAddress, string $language): Page
    {
        $this->throttle->hit($ipAddress);

        if ($criteria->salaryMin !== null && $criteria->salaryPeriodId === null) {
            throw new BusinessRuleViolation('A minimum salary needs the salary period it is meant in.', 'salary-period-required');
        }

        if ($page->page * $page->perPage > self::MAX_POSITION) {
            throw new BusinessRuleViolation('The search shows at most the first '.self::MAX_POSITION.' results; narrow it down instead.', 'page-out-of-range');
        }

        $hits = $this->reader->page($this->queries->build($criteria), $page);

        return new Page($this->items($hits->items, $language), $hits->total, $hits->request);
    }

    /**
     * @param  list<JobSearchHit>  $hits
     * @return list<PublicJobListItem>
     */
    private function items(array $hits, string $language): array
    {
        $cards = $this->publicCompanies->cards(array_values(array_unique(array_map(static fn (JobSearchHit $hit): int => $hit->companyId, $hits))));
        $jobTypes = $this->references->names('job-types', $this->ids($hits, static fn (JobSearchHit $hit): ?int => $hit->jobTypeId), $language);
        $jobLevels = $this->references->names('job-levels', $this->ids($hits, static fn (JobSearchHit $hit): ?int => $hit->jobLevelId), $language);
        $periods = $this->references->names('salary-periods', $this->ids($hits, static fn (JobSearchHit $hit): ?int => $hit->salaryPeriodId), $language);
        $provinces = $this->locations->provinceNames($this->ids($hits, static fn (JobSearchHit $hit): ?int => $hit->provinceId));
        $cities = $this->locations->cityNames($this->ids($hits, static fn (JobSearchHit $hit): ?int => $hit->cityId));

        $items = [];

        foreach ($hits as $hit) {
            // A company that became hidden between the two reads is simply left out.
            if (! isset($cards[$hit->companyId])) {
                continue;
            }

            $items[] = new PublicJobListItem(
                $hit->id,
                $hit->slug,
                $hit->title,
                $cards[$hit->companyId],
                $hit->cityId === null ? null : ($cities[$hit->cityId] ?? null),
                $hit->provinceId === null ? null : ($provinces[$hit->provinceId] ?? null),
                $this->named($hit->jobTypeId, $jobTypes),
                $this->named($hit->jobLevelId, $jobLevels),
                $hit->workMode,
                $this->salary($hit, $periods),
                $hit->deadline,
                $hit->publishedAt,
            );
        }

        return $items;
    }

    /**
     * @param  list<JobSearchHit>  $hits
     * @param  callable(JobSearchHit): ?int  $pick
     * @return list<int>
     */
    private function ids(array $hits, callable $pick): array
    {
        return array_values(array_unique(array_filter(array_map($pick, $hits), static fn (?int $id): bool => $id !== null)));
    }

    /**
     * @param  array<int, string>  $names
     * @return array{id: int, name: string}|null
     */
    private function named(?int $id, array $names): ?array
    {
        return $id === null || ! isset($names[$id]) ? null : ['id' => $id, 'name' => $names[$id]];
    }

    /**
     * @param  array<int, string>  $periods
     * @return array{min: ?int, max: ?int, period: array{id: int, name: string}|null}|null
     */
    private function salary(JobSearchHit $hit, array $periods): ?array
    {
        if (! $hit->salaryVisible || ($hit->salaryMin === null && $hit->salaryMax === null)) {
            return null;
        }

        return ['min' => $hit->salaryMin, 'max' => $hit->salaryMax, 'period' => $this->named($hit->salaryPeriodId, $periods)];
    }
}
