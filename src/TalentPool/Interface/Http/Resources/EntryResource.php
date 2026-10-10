<?php

namespace Src\TalentPool\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\TalentPool\Application\EntryView;

/**
 * A saved candidate. `candidate` and `note` are null (and `available` false) once the candidate can no longer be found; the entry is only kept so it can be removed.
 *
 * @property EntryView $resource
 */
final class EntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'available' => $this->resource->isAvailable(),
            'candidate' => $this->resource->isAvailable() ? ['slug' => $this->resource->candidateSlug, 'name' => $this->resource->candidateName] : null,
            'category' => $this->resource->categoryId === null || $this->resource->categoryName === null ? null : ['id' => $this->resource->categoryId, 'name' => $this->resource->categoryName],
            'note' => $this->resource->isAvailable() ? $this->resource->note : null,
            'saved_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
        ];
    }
}
