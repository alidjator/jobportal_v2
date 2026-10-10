<?php

namespace Src\Job\Domain;

use DateTimeImmutable;

/** One saved job as stored: which job and when. `$title` is the job's own title, kept readable after the job is no longer live. */
final readonly class SavedJob
{
    public function __construct(public int $jobId, public string $title, public DateTimeImmutable $savedAt) {}
}
