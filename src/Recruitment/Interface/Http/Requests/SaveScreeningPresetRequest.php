<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** A screening preset in full, for creating and for replacing one: its name, the optional level hint and the whole ordered list of questions (the shape of a job's list). */
final class SaveScreeningPresetRequest extends FormRequest
{
    use ScreeningItemsInput;

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'job_level_id' => ['nullable', 'integer', 'min:1'],
            ...$this->itemRules(),
        ];
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }

    public function jobLevelId(): ?int
    {
        return $this->filled('job_level_id') ? $this->integer('job_level_id') : null;
    }
}
