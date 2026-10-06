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
            'location' => [
                'province' => $location->province,
                'city' => $location->city,
                'subdistrict' => $location->subdistrict,
                'village' => $location->village,
                'postal_code' => $location->postalCode,
                'address_line' => $content->addressLine,
            ],
            'skills' => $this->resource->skills,
            'tags' => $content->tags,
            'benefits' => $this->resource->benefits,
            'published_at' => $job->publishedAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
