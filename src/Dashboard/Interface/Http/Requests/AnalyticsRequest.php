<?php

namespace Src\Dashboard\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The range, the format and, for a CSV, the table of an analysis. Whether the range is allowed is a use case rule. */
final class AnalyticsRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'format' => ['nullable', 'string', 'in:json,csv'],
            'table' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function from(): ?string
    {
        return $this->filled('from') ? $this->string('from')->toString() : null;
    }

    public function to(): ?string
    {
        return $this->filled('to') ? $this->string('to')->toString() : null;
    }

    public function wantsCsv(): bool
    {
        return $this->input('format') === 'csv';
    }

    public function table(): ?string
    {
        return $this->filled('table') ? $this->string('table')->toString() : null;
    }
}
