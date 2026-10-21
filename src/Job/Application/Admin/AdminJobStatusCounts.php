<?php

namespace Src\Job\Application\Admin;

/** How many jobs hold each status, with a zero for a status no job holds, and the total of them all. */
final readonly class AdminJobStatusCounts
{
    /** @param  array<string, int>  $byStatus  keyed by the status value, one entry per `JobStatus` */
    public function __construct(public int $all, public array $byStatus) {}
}
