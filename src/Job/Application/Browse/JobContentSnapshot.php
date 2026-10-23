<?php

namespace Src\Job\Application\Browse;

use Src\Job\Domain\JobContent;
use Src\Job\Domain\JobRepository;
use Src\Job\Domain\JobWorkPlace;
use Src\Shared\Application\References\LocationReferences;
use Src\Shared\Application\References\ReferenceValues;

/**
 * The content of a job as it is kept with an application when a candidate applies (`docs/design/phase-8-job-copy-in-application.md` section 1): what the public page shows (the salary only
 * when the job shows it) and nothing about the company, the assignees or the status. Master items carry their names in both languages, so the candidate reads them in their own.
 */
final readonly class JobContentSnapshot
{
    public const int VERSION = 1;

    private const array LANGUAGES = ['id', 'en'];

    public function __construct(
        private JobRepository $jobs,
        private ReferenceValues $references,
        private LocationReferences $locations,
    ) {}

    /** @return array<string, mixed>|null null when the job does not exist */
    public function of(int $jobId): ?array
    {
        $job = $this->jobs->find($jobId);

        if ($job === null) {
            return null;
        }

        $content = $job->content;
        $place = $this->locations->names($content->provinceId, $content->cityId, $content->subdistrictId, $content->villageId, $content->postalCodeId);

        return [
            'version' => self::VERSION,
            'title' => $content->title,
            'description' => $content->description,
            'work_mode' => $content->workMode?->value,
            'locations' => $content->workPlaces !== [] ? $this->places($content) : ($place->province === null && $content->addressLine === null ? [] : [['province' => $place->province, 'city' => $place->city, 'address_line' => $content->addressLine]]),
            'salary' => $this->salary($content),
            'skills' => $this->named('skills', $content->skillIds),
            'benefits' => $this->named('benefits', $content->benefitIds),
            'tags' => $content->tags,
            'as_of_migration' => false,
        ];
    }

    /** @return list<array{province: ?string, city: ?string, address_line: ?string}> the company locations of the job, the main one first */
    private function places(JobContent $content): array
    {
        return array_map(function (JobWorkPlace $place): array {
            $names = $this->locations->names($place->provinceId, $place->cityId, $place->subdistrictId, $place->villageId, $place->postalCodeId);

            return ['province' => $names->province, 'city' => $names->city, 'address_line' => $place->addressLine];
        }, $content->workPlaces);
    }

    /** @return array{min: ?int, max: ?int, period: array{id: int, name: array<string, string>}|null}|null */
    private function salary(JobContent $content): ?array
    {
        if (! $content->salaryVisible || ! $content->hasSalaryAmount()) {
            return null;
        }

        $period = $content->salaryPeriodId === null ? null : $this->named('salary-periods', [$content->salaryPeriodId])[0] ?? null;

        return ['min' => $content->salaryMin, 'max' => $content->salaryMax, 'period' => $period];
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, name: array<string, string>}>
     */
    private function named(string $master, array $ids): array
    {
        $items = [];

        foreach ($ids as $id) {
            $names = [];

            foreach (self::LANGUAGES as $language) {
                $name = $this->references->name($master, $id, $language);

                if ($name !== null) {
                    $names[$language] = $name;
                }
            }

            if ($names !== []) {
                $items[] = ['id' => $id, 'name' => $names];
            }
        }

        return $items;
    }
}
