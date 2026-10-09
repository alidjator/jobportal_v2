<?php

namespace Src\Job\Application\Matching;

use Src\Job\Application\Browse\JobSearchHit;
use Src\Shared\Application\Matching\JobSignals;
use Src\Shared\Application\Matching\JobSignalSource;

/** Answers the matching engine, and the other contexts that score candidates, with the signals of jobs. */
final readonly class JobSignalProvider implements JobSignalSource
{
    public function __construct(private JobSignalReader $signals, private JobPool $pool) {}

    public function forJobs(array $jobIds): array
    {
        return $this->signals->forJobs($jobIds);
    }

    public function liveNear(?int $provinceId, int $limit): array
    {
        $hits = $this->pool->hits($provinceId, $limit);
        $signals = $this->signals->forJobs(array_map(static fn (JobSearchHit $hit): int => $hit->id, $hits));

        return array_values(array_filter(
            array_map(static fn (JobSearchHit $hit): ?JobSignals => $signals[$hit->id] ?? null, $hits),
            static fn (?JobSignals $job): bool => $job !== null,
        ));
    }
}
