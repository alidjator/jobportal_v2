<?php

namespace Src\Recruitment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Domain\QuestionOption;
use Src\Recruitment\Domain\ScreeningQuestion;

/** One question of the company's library. @property ScreeningQuestion $resource */
final class ScreeningQuestionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type->value,
            'text' => $this->resource->text,
            'options' => array_map(static fn (QuestionOption $option): array => ['id' => $option->id, 'label' => $option->label], $this->resource->options),
            'archived_at' => $this->resource->archivedAt?->format(DateTimeInterface::ATOM),
            'created_at' => $this->resource->createdAt->format(DateTimeInterface::ATOM),
            'updated_at' => $this->resource->updatedAt->format(DateTimeInterface::ATOM),
        ];
    }
}
