<?php

namespace Src\Job\Application\Saved;

use DateTimeImmutable;
use Src\Job\Application\Browse\PublicJobListItem;

/**
 * One entry of a job seeker's saved jobs. `$job` is the row of the public search while the job is live; once it is closed, expired, taken down or its company
 * is hidden, `$job` is null and the entry only keeps the job's id, its title and when it was saved.
 */
final readonly class SavedJobEntry
{
    public function __construct(public int $jobId, public string $title, public DateTimeImmutable $savedAt, public ?PublicJobListItem $job) {}

    public function isAvailable(): bool
    {
        return $this->job !== null;
    }
}
