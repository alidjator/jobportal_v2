<?php

namespace Src\Job\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Job\Application\Browse\PublicJobView;

/**
 * The public page of a live job: names in the request language, the salary only when the company shows it, and the company's public card. No status, reason,
 * assignee, creator or review detail, and none of the company's contact data.
 *
 * @property PublicJobView $resource
 */
final class PublicJobResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $job = $this->resource->job;
        $content = $job->content;
        $company = $this->resource->company;
        $location = $this->resource->location;

        return [
            'id' => $job->id,
            'slug' => $job->slug.'-'.$job->id,
            'title' => $content->title,
            /** One line for search results and sharing; clients show nothing without it. */
            'summary' => $content->summary,
            'description' => $content->description,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'logo_url' => $company->logoUrl,
                'verified' => $company->verified,
            ],
            ...$this->resource->classification,
            /** Null when the company hides the number. */
            'vacancies' => $content->vacanciesVisible ? $content->vacancies : null,
            'deadline' => $content->deadline?->format('Y-m-d'),
            'work_mode' => $content->workMode?->value,
            'salary' => $this->resource->salary,
            /** Shown even when the amounts are hidden. */
            'salary_note' => $content->salaryNote,
            // The company locations of the job, the main one first. A job with an address of its own has one item whose `id`, `name` and `type` are null, and a remote job without a place has none.
            /** @var list<array{id: int|null, name: string|null, type: string|null, province: string|null, city: string|null, subdistrict: string|null, village: string|null, postal_code: string|null, address_line: string|null}> */
            'locations' => $this->resource->places !== [] ? $this->resource->places : ($location->province === null && $content->addressLine === null ? [] : [[
                'id' => null,
                'name' => null,
                'type' => null,
                'province' => $location->province,
                'city' => $location->city,
                'subdistrict' => $location->subdistrict,
                'village' => $location->village,
                'postal_code' => $location->postalCode,
                'address_line' => $content->addressLine,
            ]]),
            'skills' => $this->resource->skills,
            'tags' => $content->tags,
            'benefits' => $this->resource->benefits,
            'published_at' => $job->publishedAt?->format(DateTimeInterface::ATOM),
            // Only for a signed-in job seeker with a complete profile; `signals` is the fit of each signal as 0 to 100, null when it could not be judged.
            ...($this->resource->match === null ? [] : ['match' => ['percent' => $this->resource->match->percent, 'signals' => $this->resource->match->signals]]),
            // Only for a signed-in job seeker: whether they saved the job.
            ...($this->resource->saved === null ? [] : ['saved' => $this->resource->saved]),
        ];
    }
}
