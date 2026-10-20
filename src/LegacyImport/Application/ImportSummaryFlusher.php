<?php

namespace Src\LegacyImport\Application;

use Src\Shared\Application\Clock;

/** Writes what the importers recorded, at the end of a real run. */
final readonly class ImportSummaryFlusher
{
    public function __construct(
        private JobSeekerSummaryWriter $jobSeekers,
        private CompanySummaryWriter $companies,
        private Clock $clock,
    ) {}

    public function flush(ImportSummaryRecorder $recorder): void
    {
        $at = $this->clock->now();

        foreach ($recorder->jobSeekers() as $userId => $items) {
            $this->jobSeekers->save($userId, $items, $at);
        }

        foreach ($recorder->companies() as $companyId => $items) {
            $this->companies->save($companyId, $items, $at);
        }
    }
}
