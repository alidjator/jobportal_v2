<?php

namespace Src\Dashboard\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** `from` and `to` (Jakarta days, `to` included); both are optional (today and the 29 days after). Whether the range is short enough is a use case rule (422 `invalid-calendar-range`). */
final class ApplicantAgendaRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
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
}
