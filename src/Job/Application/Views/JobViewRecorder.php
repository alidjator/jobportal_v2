<?php

namespace Src\Job\Application\Views;

/** Writes one counted view of a job without making the public request wait for the database. Implemented by the queue. */
interface JobViewRecorder
{
    /** @param string $day the Jakarta calendar day, `Y-m-d` */
    public function record(int $jobId, string $viewerKey, string $day): void;
}
