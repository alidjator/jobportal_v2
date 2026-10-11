<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Assessment\Application\Authoring\SectionDraft;
use Src\Assessment\Domain\SectionFormat;

/** The body of adding and of changing a section. What each format allows is checked by the use case. */
final class SectionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:300'],
            'instructions' => ['nullable', 'string', 'max:6000'],
            'format' => ['required', 'string', Rule::enum(SectionFormat::class)],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'allow_skip' => ['nullable', 'boolean'],
            'allow_back' => ['nullable', 'boolean'],
            'allow_jump' => ['nullable', 'boolean'],
            'draw_count' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'settings' => ['nullable', 'array:columns,digits_per_column,seconds_per_column'],
            'settings.*' => ['integer'],
        ];
    }

    public function draft(): SectionDraft
    {
        return new SectionDraft(
            $this->string('title')->toString(),
            $this->filled('instructions') ? $this->string('instructions')->toString() : null,
            SectionFormat::from($this->string('format')->toString()),
            $this->filled('duration_seconds') ? $this->integer('duration_seconds') : null,
            $this->boolean('allow_skip', true),
            $this->boolean('allow_back', true),
            $this->boolean('allow_jump', true),
            $this->filled('draw_count') ? $this->integer('draw_count') : null,
            $this->array('settings'),
        );
    }
}
