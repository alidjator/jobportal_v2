<?php

namespace Src\Assessment\Interface\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Delivery\PracticeSection;
use Src\Assessment\Domain\Invitation;
use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\QuestionOption;
use Src\Assessment\Domain\QuestionType;
use Src\Assessment\Domain\Test;

/**
 * The practice questions of a test: the sample questions by section, each with its correct answer. A choice question lists its options with `is_correct`; a number or short-text question lists the
 * accepted answers; a long-text or video question has no answer to show. Nothing here is scored or stored.
 *
 * @property array{invitation: Invitation, test: Test, sections: list<PracticeSection>} $resource
 */
final class PracticeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'invitation_id' => $this->resource['invitation']->id,
            'test' => ['id' => $this->resource['test']->id, 'title' => $this->resource['test']->title()],
            'sections' => array_map(fn (PracticeSection $practice): array => [
                'title' => $practice->section->title,
                'format' => $practice->section->format->value,
                'questions' => array_map(fn (Question $question): array => $this->question($question), $practice->questions),
            ], $this->resource['sections']),
        ];
    }

    /** @return array<string, mixed> */
    private function question(Question $question): array
    {
        $choices = $question->type->hasChoices();

        return [
            'id' => $question->id,
            'type' => $question->type->value,
            'text' => $question->text,
            'options' => $choices ? array_map(static fn (QuestionOption $option, int $index): array => [
                'position' => $index + 1,
                'label' => $option->label,
                'is_correct' => $option->isCorrect,
            ], $question->options, array_keys($question->options)) : [],
            /** The answers a number or short-text question accepts; empty for the other types. */
            'accepted_answers' => in_array($question->type, [QuestionType::Number, QuestionType::ShortText], true)
                ? array_values(array_map(static fn (QuestionOption $option): string => $option->label, array_filter($question->options, static fn (QuestionOption $option): bool => $option->isCorrect)))
                : [],
            'video' => $question->type === QuestionType::VideoAnswer ? ['think_seconds' => $question->thinkSeconds, 'answer_seconds' => $question->answerSeconds, 'max_retakes' => $question->maxRetakes] : null,
        ];
    }
}
