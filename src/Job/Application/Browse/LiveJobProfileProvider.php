<?php

namespace Src\Job\Application\Browse;

use Src\Shared\Application\Company\CompanyDirectory;
use Src\Shared\Application\Job\JobMatcher;
use Src\Shared\Application\Job\LiveJobProfile;
use Src\Shared\Application\Job\LiveJobProfiles;
use Src\Shared\Application\References\TagRegistry;

/** A job is live when the public search finds it (asked through the same matcher, so the rule lives in one place); its facts are read once. */
final readonly class LiveJobProfileProvider implements LiveJobProfiles
{
    public function __construct(
        private JobMatcher $matcher,
        private JobProfileReader $reader,
        private TagRegistry $tags,
        private CompanyDirectory $companies,
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

        $texts = [$record->title, ...array_values($this->tags->names($record->tagIds))];

        $company = $this->companies->summaries([$record->companyId])[$record->companyId] ?? null;

        if ($company !== null) {
            $texts[] = $company->name;
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
            $texts,
        );
    }
}
