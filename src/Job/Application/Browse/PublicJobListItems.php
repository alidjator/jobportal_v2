<?php

namespace Src\Job\Application\Browse;

use Src\Shared\Application\Company\PublicCompanies;
use Src\Shared\Application\References\LocationReferences;
use Src\Shared\Application\References\ReferenceValues;

/** Turns search hits (ids only) into list items: the company card and the names in the language of the request. Used by the search and by the recommendations. */
final readonly class PublicJobListItems
{
    public function __construct(private PublicCompanies $publicCompanies, private ReferenceValues $references, private LocationReferences $locations) {}

    /**
     * @param  list<JobSearchHit>  $hits
     * @return list<PublicJobListItem>
     */
    public function of(array $hits, string $language): array
    {
        $cards = $this->publicCompanies->cards(array_values(array_unique(array_map(static fn (JobSearchHit $hit): int => $hit->companyId, $hits))));
        $jobTypes = $this->references->names('job-types', $this->ids($hits, static fn (JobSearchHit $hit): ?int => $hit->jobTypeId), $language);
        $jobLevels = $this->references->names('job-levels', $this->ids($hits, static fn (JobSearchHit $hit): ?int => $hit->jobLevelId), $language);
        $periods = $this->references->names('salary-periods', $this->ids($hits, static fn (JobSearchHit $hit): ?int => $hit->salaryPeriodId), $language);
        $provinces = $this->locations->provinceNames($this->ids($hits, static fn (JobSearchHit $hit): ?int => $hit->provinceId));
        $cities = $this->locations->cityNames($this->ids($hits, static fn (JobSearchHit $hit): ?int => $hit->cityId));

        $items = [];

        foreach ($hits as $hit) {
            // A company that became hidden between the two reads is simply left out.
            if (! isset($cards[$hit->companyId])) {
                continue;
            }

            $items[] = new PublicJobListItem(
                $hit->id,
                $hit->slug,
                $hit->title,
                $cards[$hit->companyId],
                $hit->cityId === null ? null : ($cities[$hit->cityId] ?? null),
                $hit->provinceId === null ? null : ($provinces[$hit->provinceId] ?? null),
                $this->named($hit->jobTypeId, $jobTypes),
                $this->named($hit->jobLevelId, $jobLevels),
                $hit->workMode,
                $this->salary($hit, $periods),
                $hit->deadline,
                $hit->publishedAt,
            );
        }

        return $items;
    }

    /**
     * @param  list<JobSearchHit>  $hits
     * @param  callable(JobSearchHit): ?int  $pick
     * @return list<int>
     */
    private function ids(array $hits, callable $pick): array
    {
        return array_values(array_unique(array_filter(array_map($pick, $hits), static fn (?int $id): bool => $id !== null)));
    }

    /**
     * @param  array<int, string>  $names
     * @return array{id: int, name: string}|null
     */
    private function named(?int $id, array $names): ?array
    {
        return $id === null || ! isset($names[$id]) ? null : ['id' => $id, 'name' => $names[$id]];
    }

    /**
     * @param  array<int, string>  $periods
     * @return array{min: ?int, max: ?int, period: array{id: int, name: string}|null}|null
     */
    private function salary(JobSearchHit $hit, array $periods): ?array
    {
        if (! $hit->salaryVisible || ($hit->salaryMin === null && $hit->salaryMax === null)) {
            return null;
        }

        return ['min' => $hit->salaryMin, 'max' => $hit->salaryMax, 'period' => $this->named($hit->salaryPeriodId, $periods)];
    }
}
