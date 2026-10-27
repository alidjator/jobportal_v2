<?php

namespace Src\Job\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Job\Application\JobListItem;
use Src\Job\Domain\JobStatus;

/**
 * One row of the company's job list.
 *
 * @property JobListItem $resource
 */
final class JobListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'summary' => $this->resource->summary,
            /** @var JobStatus */
            'status' => $this->resource->status->value,
            'deadline' => $this->resource->deadline?->format('Y-m-d'),
            'vacancies' => $this->resource->vacancies,
            'hires_count' => $this->resource->hiresCount,
            'filled' => $this->resource->filled(),
            'assignees_count' => $this->resource->assigneesCount,
            /** Active applications. */
            'applications_count' => $this->resource->applicationsCount,
            'work_mode' => $this->resource->workMode?->value,
            /** The job's own address; a job with company locations shows its main one. */
            'location' => ['city' => $this->resource->city, 'province' => $this->resource->province],
            /** @var array{id: int, name: string|null}|null */
            'created_by' => $this->resource->createdBy === null ? null : ['id' => $this->resource->createdBy, 'name' => $this->resource->createdByName],
            'published_at' => $this->resource->publishedAt?->format(DateTimeInterface::ATOM),
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $this->resource->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
