<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Send only what changes: `text`, and/or `options` as the whole new list of `{id?, label}`. The length limits are checked by the use case. */
final class UpdateScreeningQuestionRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'text' => ['required_without:options', 'string', 'max:1000'],
            'options' => ['required_without:text', 'array', 'max:50'],
            'options.*' => ['array:id,label'],
            'options.*.id' => ['nullable', 'integer', 'min:1'],
            'options.*.label' => ['required', 'string', 'max:1000'],
        ];
    }

    public function text(): ?string
    {
        return $this->has('text') ? $this->string('text')->toString() : null;
    }

    /** @return list<array{id: int|null, label: string}>|null */
    public function options(): ?array
    {
        if (! $this->has('options')) {
            return null;
        }

        return array_values(array_map(
            static fn (array $option): array => ['id' => isset($option['id']) ? (int) $option['id'] : null, 'label' => (string) $option['label']],
            $this->array('options'),
        ));
    }
}
