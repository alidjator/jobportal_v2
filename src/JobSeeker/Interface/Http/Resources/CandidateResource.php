<?php

namespace Src\JobSeeker\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\JobSeeker\Application\Candidates\CandidateProfileView;

/** A candidate's profile as a company finds it. `contact` is null unless the member already sees an application of the candidate. @property CandidateProfileView $resource */
final class CandidateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $profile = $this->resource->profile;

        return [
            'slug' => $this->resource->slug,
            'name' => $this->resource->name,
            'has_avatar' => $this->resource->hasAvatar,
            'headline' => $profile->headline,
            'summary' => $profile->summary,
            'province' => $this->resource->province,
            'city' => $this->resource->city,
            'experience_level' => $this->resource->experienceLevel,
            'availability' => $profile->availability?->value,
            'available_from' => $profile->availableFrom?->format('Y-m-d'),
            'expected_salary' => $this->resource->expectedSalary,
            'experiences' => array_map(static fn (array $row): array => [
                'job_title' => $row['entry']->jobTitle,
                'company_name' => $row['entry']->companyName,
                'job_type' => $row['job_type'],
                'location_text' => $row['entry']->locationText,
                'start_date' => $row['entry']->startDate->format('Y-m'),
                'end_date' => $row['entry']->endDate?->format('Y-m'),
                'is_current' => $row['entry']->isCurrent,
                'description' => $row['entry']->description,
            ], $this->resource->experiences),
            'educations' => array_map(static fn (array $row): array => [
                'level' => $row['level'],
                'institution' => $row['entry']->institution,
                'field_of_study' => $row['entry']->fieldOfStudy,
                'start_year' => $row['entry']->startYear,
                'end_year' => $row['entry']->endYear,
                'is_current' => $row['entry']->isCurrent,
                'gpa' => $row['entry']->gpa,
                'description' => $row['entry']->description,
            ], $this->resource->educations),
            'skills' => $this->resource->skills,
            'languages' => $this->resource->languages,
            'resumes' => ResumeResource::collection($this->resource->resumes)->resolve($request),
            'contact' => $this->resource->contact,
            'updated_at' => $this->resource->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
