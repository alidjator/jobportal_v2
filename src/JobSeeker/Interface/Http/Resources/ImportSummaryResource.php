<?php

namespace Src\JobSeeker\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\JobSeeker\Application\ImportSummary\ImportSummaryNotes;
use Src\JobSeeker\Domain\ImportSummary;

/**
 * The import summary: the stored items (a code with a count and, for some, counts per reason) and the `notes` that are true for every imported job seeker. The client writes and translates the text.
 *
 * @property ImportSummary $resource
 */
final class ImportSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'kind' => 'job_seeker',
            'imported_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
            'dismissed_at' => $this->resource->dismissedAt?->format(DateTimeInterface::ATOM),
            'items' => $this->resource->items,
            'notes' => ImportSummaryNotes::codes(),
        ];
    }
}
