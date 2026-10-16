<?php

namespace Src\Job\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The stage from which a hiring manager sees applications; null returns to the company's first interview stage. */
final class SetHiringManagerBoundaryRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['stage_id' => ['present', 'nullable', 'integer', 'min:1']];
    }

    public function stageId(): ?int
    {
        return $this->input('stage_id') === null ? null : $this->integer('stage_id');
    }
}
