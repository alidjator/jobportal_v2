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
            'description' => $content->description,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'logo_url' => $company->logoUrl,
                'verified' => $company->verified,
            ],
            ...$this->resource->classification,
            'vacancies' => $content->vacancies,
            'deadline' => $content->deadline?->format('Y-m-d'),
            'work_mode' => $content->workMode?->value,
            'salary' => $this->resource->salary,
            // Until a job can have several work locations (#20) the list holds one item built from the job's own address, or none when the job has no province and no street line (a remote job);
            // `id`, `name` and `type` are those of a company work location and are null.
            'locations' => $location->province === null && $content->addressLine === null ? [] : [[
                'id' => null,
                'name' => null,
                'type' => null,
                'province' => $location->province,
                'city' => $location->city,
                'subdistrict' => $location->subdistrict,
                'village' => $location->village,
                'postal_code' => $location->postalCode,
                'address_line' => $content->addressLine,
            ]],
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
