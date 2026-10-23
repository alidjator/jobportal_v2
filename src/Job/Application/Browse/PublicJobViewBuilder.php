<?php

namespace Src\Job\Application\Browse;

use Src\Job\Domain\Job;
use Src\Job\Domain\JobWorkPlace;
use Src\Shared\Application\Company\PublicCompanyCard;
use Src\Shared\Application\References\LocationReferences;
use Src\Shared\Application\References\ReferenceValues;

/** Builds the public view of a job from its saved content: the names in the language of the request, the salary the company shows and the places. It does not decide whether the job may be shown. */
final readonly class PublicJobViewBuilder
{
    /** API field => [content property, master URL name]. */
    private const array CLASSIFICATIONS = [
        'category' => ['categoryId', 'job-categories'],
        'role' => ['roleId', 'job-roles'],
        'job_type' => ['jobTypeId', 'job-types'],
        'job_level' => ['jobLevelId', 'job-levels'],
        'work_time' => ['workTimeId', 'work-times'],
        'experience_level' => ['experienceLevelId', 'experience-levels'],
        'education_level' => ['educationLevelId', 'education-levels'],
    ];

    public function __construct(
        private ReferenceValues $references,
        private LocationReferences $locations,
    ) {}

    public function build(Job $job, PublicCompanyCard $company, string $language): PublicJobView
    {
        $content = $job->content;

        $classification = [];

        foreach (self::CLASSIFICATIONS as $field => [$property, $master]) {
            $classification[$field] = $this->named($master, $content->{$property}, $language);
        }

        return new PublicJobView(
            $job,
            $company,
            $classification,
            $this->salary($job, $language),
            $this->locations->names($content->provinceId, $content->cityId, $content->subdistrictId, $content->villageId, $content->postalCodeId),
            $this->namedList('skills', $content->skillIds, $language),
            $this->namedList('benefits', $content->benefitIds, $language),
            places: $this->places($content->workPlaces),
        );
    }

    /**
     * @param  list<JobWorkPlace>  $places
     * @return list<array{id: int, name: string, type: string, province: ?string, city: ?string, subdistrict: ?string, village: ?string, postal_code: ?string, address_line: ?string}>
     */
    public function places(array $places): array
    {
        return array_map(fn (JobWorkPlace $place): array => $this->place($place), $places);
    }

    /** @return array{id: int, name: string, type: string, province: ?string, city: ?string, subdistrict: ?string, village: ?string, postal_code: ?string, address_line: ?string} */
    private function place(JobWorkPlace $place): array
    {
        $names = $this->locations->names($place->provinceId, $place->cityId, $place->subdistrictId, $place->villageId, $place->postalCodeId);

        return [
            'id' => $place->locationId,
            'name' => $place->name,
            'type' => $place->type,
            'province' => $names->province,
            'city' => $names->city,
            'subdistrict' => $names->subdistrict,
            'village' => $names->village,
            'postal_code' => $names->postalCode,
            'address_line' => $place->addressLine,
        ];
    }

    /** @return array{min: ?int, max: ?int, period: array{id: int, name: string}|null}|null */
    private function salary(Job $job, string $language): ?array
    {
        $content = $job->content;

        if (! $content->salaryVisible || ! $content->hasSalaryAmount()) {
            return null;
        }

        return ['min' => $content->salaryMin, 'max' => $content->salaryMax, 'period' => $this->named('salary-periods', $content->salaryPeriodId, $language)];
    }

    /** @return array{id: int, name: string}|null */
    private function named(string $master, ?int $id, string $language): ?array
    {
        $name = $id === null ? null : $this->references->name($master, $id, $language);

        return $id === null || $name === null ? null : ['id' => $id, 'name' => $name];
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, name: string}>
     */
    private function namedList(string $master, array $ids, string $language): array
    {
        $items = [];

        foreach ($ids as $id) {
            $item = $this->named($master, $id, $language);

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }
}
