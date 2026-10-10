<?php

namespace Src\Recruitment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Recruitment\Domain\IdealAnswer;
use Src\Recruitment\Domain\JobQuestion;
use Src\Recruitment\Domain\QuestionOption;
use Src\Recruitment\Domain\QuestionType;

/**
 * A question of a job as its own company sees it, with the flags and the ideal answer.
 *
 * @property JobQuestion $resource
 */
final class JobQuestionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $question = $this->resource->question;

        return [
            'position' => $this->resource->position,
            'question_id' => $question->id,
            'type' => $question->type->value,
            'text' => $question->text,
            /** @var list<array{id: int, label: string}> */
            'options' => array_map(static fn (QuestionOption $option): array => ['id' => $option->id, 'label' => $option->label], $question->options),
            'archived' => $question->isArchived(),
            'required' => $this->resource->isRequired,
            'must_have' => $this->resource->isMustHave,
            'ideal' => $this->resource->ideal === null ? null : self::ideal($this->resource->ideal),
        ];
    }

    /** @return array<string, mixed> */
    private static function ideal(IdealAnswer $ideal): array
    {
        return match ($ideal->type) {
            QuestionType::YesNo => ['yes' => $ideal->yes],
            QuestionType::SingleChoice, QuestionType::MultipleChoice => ['option_ids' => $ideal->optionIds],
            QuestionType::Number => ['min' => $ideal->min, 'max' => $ideal->max],
            QuestionType::Text => [],
        };
    }
}
