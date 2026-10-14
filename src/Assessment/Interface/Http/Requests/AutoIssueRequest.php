<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The automatic issue of the reports of a battery: on or off, and the responsible psychologist when on. */
final class AutoIssueRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'responsible_psychologist_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function enabled(): bool
    {
        return $this->boolean('enabled');
    }

    public function responsiblePsychologistId(): ?int
    {
        return $this->filled('responsible_psychologist_id') ? $this->integer('responsible_psychologist_id') : null;
    }
}
