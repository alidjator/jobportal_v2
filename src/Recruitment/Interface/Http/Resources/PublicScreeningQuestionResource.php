<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Application\PublicScreeningQuestion;
use Src\Recruitment\Domain\QuestionOption;

/** A question as an applicant sees it: never the must-have flag or the ideal answer. @property PublicScreeningQuestion $resource */
final class PublicScreeningQuestionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type->value,
            'text' => $this->resource->text,
            'options' => array_map(static fn (QuestionOption $option): array => ['id' => $option->id, 'label' => $option->label], $this->resource->options),
            'required' => $this->resource->required,
            'position' => $this->resource->position,
        ];
    }
}
