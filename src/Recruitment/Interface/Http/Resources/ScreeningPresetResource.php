<?php

namespace Src\Recruitment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Domain\ScreeningPreset;

/**
 * One screening preset of the company, with its questions as a job's list shows them.
 *
 * @property ScreeningPreset $resource
 */
final class ScreeningPresetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'job_level_id' => $this->resource->jobLevelId,
            'question_count' => count($this->resource->questions),
            'questions' => JobQuestionResource::collection($this->resource->questions),
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $this->resource->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
