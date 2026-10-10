<?php

namespace Src\Job\Infrastructure\Insight;

use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository;
use Src\Job\Application\Insight\SalaryFigures;
use Src\Job\Application\Insight\SalaryGroup;
use Src\Job\Application\Insight\SalaryInsightReader;

/**
 * Keeps the figures of a group for an hour (a group that was too small is kept too, so a widening visitor does not run six queries every time). The key holds
 * everything the figures depend on: the group, the window, the minimums and the hidden companies. A new job shows within the hour.
 */
final readonly class CachedSalaryInsightReader implements SalaryInsightReader
{
    public const int SECONDS = 3600;

    public function __construct(private DatabaseSalaryInsightReader $reader, private Repository $cache) {}

    public function figures(SalaryGroup $group, DateTimeImmutable $since, int $monthlyPeriodId, int $minJobs, int $minCompanies, array $excludedCompanyIds): ?SalaryFigures
    {
        sort($excludedCompanyIds);
        $key = 'salary-insight:'.sha1(implode('#', [$group->key(), $since->format('Y-m-d'), $monthlyPeriodId, $minJobs, $minCompanies, implode(',', $excludedCompanyIds)]));

        /** @var array{jobs: int, companies: int, p25: int, median: int, p75: int, at: string}|false|null $stored */
        $stored = $this->cache->get($key);

        if ($stored === null) {
            $figures = $this->reader->figures($group, $since, $monthlyPeriodId, $minJobs, $minCompanies, $excludedCompanyIds);
            $this->cache->put($key, $figures === null ? false : [
                'jobs' => $figures->jobs, 'companies' => $figures->companies, 'p25' => $figures->p25, 'median' => $figures->median, 'p75' => $figures->p75,
                'at' => $figures->computedAt->format(DATE_ATOM),
            ], self::SECONDS);

            return $figures;
        }

        return $stored === false ? null : new SalaryFigures($stored['jobs'], $stored['companies'], $stored['p25'], $stored['median'], $stored['p75'], new DateTimeImmutable($stored['at']));
    }
}
