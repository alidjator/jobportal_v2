<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The period of the hires export, `from` and `to` as `YYYY-MM-DD` (Jakarta days), and the optional `filter[job_id]`. The length of the period is checked by the use case (`invalid-export-range`). */
final class ExportHiresRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'string', 'date_format:Y-m-d'],
            'to' => ['required', 'string', 'date_format:Y-m-d'],
            'filter' => ['nullable', 'array:job_id'],
            'filter.job_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function fromDay(): string
    {
        return $this->string('from')->toString();
    }

    public function toDay(): string
    {
        return $this->string('to')->toString();
    }

    public function jobId(): ?int
    {
        return $this->filled('filter.job_id') ? $this->integer('filter.job_id') : null;
    }
}
