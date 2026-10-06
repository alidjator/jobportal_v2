<?php

namespace Src\JobSeeker\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\JobSeeker\Application\MyProfile;

/** The owner's profile: master values and location levels as ids (clients read the names from the masters), and whether it is complete enough to apply. @property MyProfile $resource */
final class MyProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $profile = $this->resource->profile;

        return [
            'phone' => $profile->phone,
            'whatsapp' => $profile->whatsapp,
            'birth_date' => $profile->birthDate?->format('Y-m-d'),
            'gender' => $profile->gender?->value,
            'marital_status' => $profile->maritalStatus?->value,
            'religion_id' => $profile->religionId,
            'nationality_id' => $profile->nationalityId,
            'expected_salary' => $profile->expectedSalary,
            'expected_salary_period_id' => $profile->expectedSalaryPeriodId,
            'experience_level_id' => $profile->experienceLevelId,
            'availability' => $profile->availability?->value,
            'available_from' => $profile->availableFrom?->format('Y-m-d'),
            'province_id' => $profile->provinceId,
            'city_id' => $profile->cityId,
            'subdistrict_id' => $profile->subdistrictId,
            'village_id' => $profile->villageId,
            'postal_code_id' => $profile->postalCodeId,
            'address_line' => $profile->addressLine,
            'headline' => $profile->headline,
            'summary' => $profile->summary,
            'is_discoverable' => $profile->isDiscoverable,
            'resume_discoverable' => $profile->resumeDiscoverable,
            'experiences' => ExperienceResource::collection($this->resource->experiences)->resolve($request),
            'educations' => EducationResource::collection($this->resource->educations)->resolve($request),
            'skills' => $this->resource->skills,
            'languages' => array_map(static fn (array $language): array => [...$language, 'proficiency' => $language['proficiency']->value], $this->resource->languages),
            'links' => array_map(static fn ($link): array => ['type' => $link->type->value, 'url' => $link->url], $this->resource->links),
            'resumes' => ResumeResource::collection($this->resource->resumes)->resolve($request),
            'completeness' => ['complete' => $this->resource->completeness->isComplete(), 'missing' => $this->resource->completeness->missing],
        ];
    }
}
