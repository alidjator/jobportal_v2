<?php

namespace Src\Job\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Job\Application\Admin\AdminJobListItem;

/**
 * One row of the admin job list.
 *
 * @property AdminJobListItem $resource
 */
final class AdminJobListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $company = $this->resource->company;

        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'status' => $this->resource->status->value,
            'company' => $company === null ? ['id' => $this->resource->companyId, 'name' => null, 'slug' => null] : ['id' => $company->id, 'name' => $company->name, 'slug' => $company->slug],
            'deadline' => $this->resource->deadline?->format('Y-m-d'),
            'vacancies' => $this->resource->vacancies,
            'assignees_count' => $this->resource->assigneesCount,
            // Only the review queue (`filter[status]=pending_review`) knows; null in any other list.
            'flagged' => $this->resource->flagged,
            'published_at' => $this->resource->publishedAt?->format(DateTimeInterface::ATOM),
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $this->resource->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
