<?php

namespace Src\Job\Application\Browse;

use Src\Shared\Application\Job\JobMatcher;
use Src\Shared\Application\Job\LiveJobProfile;
use Src\Shared\Application\Job\LiveJobProfiles;

/** A job is live when the public search finds it (asked through the same matcher, so the rule lives in one place); its facts are read once. */
final readonly class LiveJobProfileProvider implements LiveJobProfiles
{
    public function __construct(
        private JobMatcher $matcher,
        private JobProfileReader $reader,
    ) {}

    public function of(int $jobId): ?LiveJobProfile
    {
        if ($this->matcher->match([], null, null, $jobId, 1)->total === 0) {
            return null;
        }

        $record = $this->reader->find($jobId);

        if (! $record instanceof JobProfileRecord || $record->publishedAt === null) {
            return null;
        }

        return new LiveJobProfile(
            $record->jobId,
            $record->publishedAt,
            $record->ids,
            $record->skillIds,
            $record->workMode,
            // A minimum salary only matches a salary the company shows, in the same period.
            $record->salaryVisible ? $record->salaryPeriodId : null,
            $record->salaryVisible ? $record->salaryCeiling : null,
            $record->searchVector,
        );
    }
}
