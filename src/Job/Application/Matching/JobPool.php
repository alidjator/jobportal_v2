<?php

namespace Src\Job\Application\Matching;

use Src\Job\Application\Browse\JobSearchCriteria;
use Src\Job\Application\Browse\JobSearchHit;
use Src\Job\Application\Browse\JobSearchQueryBuilder;
use Src\Job\Application\Browse\JobSearchReader;
use Src\Job\Domain\WorkMode;
use Src\Shared\Application\Pagination\PageRequest;

/**
 * The live jobs a job seeker's recommendations are scored from: the newest ones that are remote or in their province. It asks the public search reader, so what
 * is live here is exactly what the public search shows.
 */
final readonly class JobPool
{
    public function __construct(private JobSearchReader $reader, private JobSearchQueryBuilder $queries) {}

    /**
     * @return list<JobSearchHit> the newest first, at most `$limit`
     */
    public function hits(?int $provinceId, int $limit): array
    {
        $criteria = $provinceId === null
            ? [new JobSearchCriteria]
            : [new JobSearchCriteria(provinceIds: [$provinceId]), new JobSearchCriteria(workModes: [WorkMode::Remote])];

        $hits = [];

        foreach ($criteria as $one) {
            foreach ($this->collect($one, $limit) as $hit) {
                $hits[$hit->id] = $hit;
            }
        }

        usort($hits, static fn (JobSearchHit $a, JobSearchHit $b): int => [$b->publishedAt, $b->id] <=> [$a->publishedAt, $a->id]);

        return array_slice($hits, 0, $limit);
    }

    /** @return list<JobSearchHit> */
    private function collect(JobSearchCriteria $criteria, int $limit): array
    {
        $query = $this->queries->build($criteria);
        $hits = [];

        for ($page = 1; count($hits) < $limit; $page++) {
            $result = $this->reader->page($query, new PageRequest($page, PageRequest::MAX_PER_PAGE));
            $hits = [...$hits, ...$result->items];

            if ($result->items === [] || count($hits) >= $result->total) {
                break;
            }
        }

        return $hits;
    }
}
