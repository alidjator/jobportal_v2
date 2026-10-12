<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Assessment\Application\Authoring\QuestionDraft;
use Src\Assessment\Domain\QuestionOption;
use Src\Assessment\Domain\QuestionType;

/** The body of adding and of changing a question. What each type allows is checked by the use case; an empty `think_seconds` means the think time of a video answer is not limited. */
final class QuestionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(QuestionType::class)],
            'text' => ['required', 'string', 'max:6000'],
            'points' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'scale_key' => ['nullable', 'string', 'max:100'],
            'is_sample' => ['nullable', 'boolean'],
            'think_seconds' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'answer_seconds' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'max_retakes' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'options' => ['nullable', 'array', 'max:20'],
            'options.*' => ['array:label,is_correct,score_value,scale_key'],
            'options.*.label' => ['required', 'string', 'max:1000'],
            'options.*.is_correct' => ['nullable', 'boolean'],
            'options.*.score_value' => ['nullable', 'integer', 'min:-1000', 'max:1000'],
            'options.*.scale_key' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function draft(): QuestionDraft
    {
        $options = [];

        foreach ($this->array('options') as $option) {
            $options[] = new QuestionOption(
                (string) $option['label'],
                (bool) ($option['is_correct'] ?? false),
                isset($option['score_value']) ? (int) $option['score_value'] : null,
                isset($option['scale_key']) && $option['scale_key'] !== '' ? (string) $option['scale_key'] : null,
            );
        }

        return new QuestionDraft(
            QuestionType::from($this->string('type')->toString()),
            $this->string('text')->toString(),
            $this->integer('points', 1),
            $this->filled('scale_key') ? $this->string('scale_key')->toString() : null,
            $this->boolean('is_sample'),
            $options,
            $this->filled('think_seconds') ? $this->integer('think_seconds') : null,
            $this->filled('answer_seconds') ? $this->integer('answer_seconds') : null,
            $this->filled('max_retakes') ? $this->integer('max_retakes') : null,
        );
    }
}
