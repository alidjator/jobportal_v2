<?php

namespace Src\Recruitment\Application;

use Src\Recruitment\Domain\ApplicationJobSnapshots;
use Src\Shared\Application\Job\JobDirectory;

/**
 * Fills the copy of the job content (`recruitment_applications.job_snapshot`) of the applications made before the copy existed, once, from the job as it is now, and marks it
 * `as_of_migration` so the candidate is told it may have changed since they applied (`docs/design/phase-8-job-copy-in-application.md` section 2). An erased application is left out. It
 * works in id order in batches, builds the content of each job once per run, and running it again fills only what is still missing.
 */
final readonly class BackfillJobSnapshots
{
    private const int BATCH = 200;

    public function __construct(private ApplicationJobSnapshots $snapshots, private JobDirectory $jobs) {}

    /** @return int how many applications got a copy */
    public function execute(): int
    {
        $filled = 0;
        $after = 0;
        /** @var array<int, array<string, mixed>|null> $content */
        $content = [];

        while (($batch = $this->snapshots->withoutSnapshot($after, self::BATCH)) !== []) {
            foreach ($batch as $application) {
                $content[$application['job_id']] ??= $this->jobs->contentSnapshot($application['job_id']);

                if ($content[$application['job_id']] !== null) {
                    $this->snapshots->save($application['id'], [...$content[$application['job_id']], 'as_of_migration' => true]);
                    $filled++;
                }

                $after = $application['id'];
            }
        }

        return $filled;
    }
}
