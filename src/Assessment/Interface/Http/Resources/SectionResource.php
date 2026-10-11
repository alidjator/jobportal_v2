<?php

namespace Src\Assessment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Domain\Section;

/**
 * A section. `question_count` is present when the section is read with its test.
 *
 * @property Section $resource
 */
final class SectionResource extends JsonResource
{
    public function __construct(Section $resource, private readonly ?int $questionCount = null)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'position' => $this->resource->position,
            'title' => $this->resource->title,
            'instructions' => $this->resource->instructions,
            'format' => $this->resource->format->value,
            'duration_seconds' => $this->resource->durationSeconds,
            'allow_skip' => $this->resource->allowSkip,
            'allow_back' => $this->resource->allowBack,
            'allow_jump' => $this->resource->allowJump,
            'draw_count' => $this->resource->drawCount,
            'settings' => (object) $this->resource->settings,
            'question_count' => $this->when($this->questionCount !== null, $this->questionCount),
        ];
    }
}
