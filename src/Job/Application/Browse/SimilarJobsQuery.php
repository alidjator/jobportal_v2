<?php

namespace Src\Job\Application\Browse;

/**
 * What the similar jobs of one job are chosen by: the job itself (left out), its role and category, its place and its level with the levels one step away.
 * `$levelIds` holds the level of the job and its neighbours; empty when the job has no level.
 */
final readonly class SimilarJobsQuery
{
    /** @param  list<int>  $levelIds */
    public function __construct(
        public int $jobId,
        public ?int $roleId,
        public ?int $categoryId,
        public ?int $provinceId,
        public ?int $cityId,
        public array $levelIds,
    ) {}
}
