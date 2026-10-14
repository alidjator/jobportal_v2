<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Domain\Test;
use Src\Assessment\Domain\TestKind;

/** @property Test $resource */
final class TestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'kind' => $this->resource->kind()->value,
            'title' => $this->resource->title(),
            'description' => $this->resource->description(),
            'instructions' => $this->resource->instructions(),
            'measured_capabilities' => $this->resource->capabilities(),
            'status' => $this->resource->status()->value,
            'auto_issue' => $this->when($this->resource->kind() === TestKind::Psychological, $this->resource->autoIssue()),
            'responsible_psychologist_id' => $this->when($this->resource->kind() === TestKind::Psychological, $this->resource->responsiblePsychologistId()),
            'published_at' => $this->resource->publishedAt()?->format(DateTimeInterface::ATOM),
            'created_at' => $this->resource->createdAt?->format(DateTimeInterface::ATOM),
        ];
    }
}
