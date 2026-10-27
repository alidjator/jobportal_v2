<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The preset to copy into a job. */
final class ApplyScreeningPresetRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['preset_id' => ['required', 'integer', 'min:1']];
    }

    public function presetId(): int
    {
        return $this->integer('preset_id');
    }
}
