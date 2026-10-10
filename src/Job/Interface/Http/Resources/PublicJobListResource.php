<?php

namespace Src\Job\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Job\Application\Browse\PublicJobListItem;

/** One row of the public job search: enough to choose a job; the whole page is `GET /jobs/{id}`. @property PublicJobListItem $resource */
final class PublicJobListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $company = $this->resource->company;

        return [
            'id' => $this->resource->id,
            'slug' => $this->resource->slug.'-'.$this->resource->id,
            'title' => $this->resource->title,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'logo_url' => $company->logoUrl,
                'verified' => $company->verified,
            ],
            'location' => ['city' => $this->resource->city, 'province' => $this->resource->province],
            'job_type' => $this->resource->jobType,
            'job_level' => $this->resource->jobLevel,
            'work_mode' => $this->resource->workMode?->value,
            'salary' => $this->resource->salary,
            'deadline' => $this->resource->deadline?->format('Y-m-d'),
            'published_at' => $this->resource->publishedAt?->format(DateTimeInterface::ATOM),
            // Only for a signed-in job seeker with a complete profile; the percentage is null when too little is known about the job.
            ...($this->resource->match === null ? [] : ['match' => ['percent' => $this->resource->match->percent]]),
            // Only for a signed-in job seeker: whether they saved the job.
            ...($this->resource->saved === null ? [] : ['saved' => $this->resource->saved]),
        ];
    }
}
