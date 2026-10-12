<?php

namespace Src\Assessment\Interface\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Assessment\Application\Attempts\AttemptView;
use Src\Assessment\Application\Attempts\DeliveredQuestion;
use Src\Assessment\Domain\Answer;
use Src\Assessment\Domain\Question;
use Src\Assessment\Domain\QuestionOption;
use Src\Assessment\Domain\QuestionType;

/**
 * An attempt as the candidate sees it. The server's clock is part of it (`server_time`), so the browser can draw the time left. The questions show the
 * options of a choice question and nothing that gives the answer away: no correct flags, no scores, and no accepted answers of a number or short-text question.
 *
 * @property AttemptView $resource
 */
final class AttemptResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $context = $this->resource->context;
        $attempt = $context->attempt;

        $data = [
            'invitation_id' => $context->invitation->id,
            'status' => $this->resource->status(),
            'server_time' => $context->now->format(DateTimeInterface::ATOM),
            'started_at' => $attempt->startedAt->format(DateTimeInterface::ATOM),
            'ends_at' => $attempt->endsAt->format(DateTimeInterface::ATOM),
            'submitted_at' => $attempt->submittedAt?->format(DateTimeInterface::ATOM),
            'auto_submitted' => $attempt->autoSubmitted,
        ];

        if ($this->resource->status() !== 'in_progress') {
            return $data;
        }

        $section = $context->section();

        $data['section'] = [
            'position' => $attempt->sectionPosition,
            'total' => count($context->sections),
            'title' => $section->title,
            'instructions' => $section->instructions,
            'format' => $section->format->value,
            'duration_seconds' => $section->durationSeconds,
            'opened_at' => $attempt->sectionOpenedAt->format(DateTimeInterface::ATOM),
            'ends_at' => $attempt->sectionDeadline($context->durations)->format(DateTimeInterface::ATOM),
            'allow_skip' => $section->allowSkip,
            'allow_back' => $section->allowBack,
            'allow_jump' => $section->allowJump,
            'question_count' => $this->resource->questionCount,
            'current_index' => $attempt->questionCursor,
        ];

        if ($this->resource->speed !== null) {
            $speed = $this->resource->speed;
            $data['speed'] = [
                'column' => $speed['column'],
                'columns' => $speed['columns'],
                'digits' => $speed['digits'],
                'column_ends_at' => $speed['column_ends_at']->format(DateTimeInterface::ATOM),
                'submitted_columns' => $speed['submitted_columns'],
            ];

            return $data;
        }

        $data['questions'] = array_map(fn (DeliveredQuestion $delivered): array => $this->question($delivered), $this->resource->questions);

        return $data;
    }

    /** @return array<string, mixed> */
    private function question(DeliveredQuestion $delivered): array
    {
        $question = $delivered->question;

        return [
            'index' => $delivered->index,
            'id' => $question->id,
            'type' => $question->type->value,
            'text' => $question->text,
            'options' => $this->options($question),
            'answer' => $delivered->answer instanceof Answer ? [
                'option_positions' => $delivered->answer->optionPositions,
                'number' => $delivered->answer->numberValue,
                'text' => $delivered->answer->textValue,
            ] : null,
            'video' => $question->type === QuestionType::VideoAnswer ? ['think_seconds' => $question->thinkSeconds, 'answer_seconds' => $question->answerSeconds, 'max_retakes' => $question->maxRetakes] : null,
            'drawing' => $delivered->drawing === null ? null : ['source' => $delivered->drawing->source->value, 'size_bytes' => $delivered->drawing->sizeBytes],
        ];
    }

    /** @return list<array{position: int, label: string}> */
    private function options(Question $question): array
    {
        if (! $question->type->hasChoices()) {
            return [];
        }

        return array_map(static fn (QuestionOption $option, int $index): array => ['position' => $index + 1, 'label' => $option->label], $question->options, array_keys($question->options));
    }
}
