<?php

namespace Src\Job\Application;

use Src\Job\Domain\JobStatus;

/** How one job did over the last 30 Jakarta days: its totals and a full daily series of views and applications. */
final readonly class JobPerformance
{
    /**
     * @param  list<array{day: string, count: int}>  $viewsDaily
     * @param  list<array{day: string, count: int}>  $applicationsDaily
     */
    public function __construct(
        public int $jobId,
        public string $title,
        public JobStatus $status,
        public string $from,
        public string $to,
        public int $views,
        public int $applications,
        public array $viewsDaily,
        public array $applicationsDaily,
    ) {}

    /** Applications per counted view, null while there is no view. */
    public function applicationsPerView(): ?float
    {
        return $this->views === 0 ? null : round($this->applications / $this->views, 4);
    }
}
