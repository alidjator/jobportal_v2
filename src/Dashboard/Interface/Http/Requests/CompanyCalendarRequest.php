<?php

namespace Src\Dashboard\Interface\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Src\Shared\Application\Calendar\CalendarEvent;

/**
 * `from` and `to` (Jakarta days, `to` included) and the filters `filter[type]` (a comma list of the event types), `filter[job_id]` and `filter[mine]`. Whether the range is short enough is a
 * use case rule (422 `invalid-calendar-range`).
 */
final class CompanyCalendarRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
            'filter' => ['nullable', 'array:type,job_id,mine'],
            'filter.type' => ['nullable', 'string', 'max:100'],
            'filter.job_id' => ['nullable', 'integer', 'min:1'],
            'filter.mine' => ['nullable', 'in:true,false,1,0'],
        ];
    }

    /** @return list<string> */
    public function types(): array
    {
        $given = $this->filled('filter.type') ? array_values(array_unique(array_filter(array_map('trim', explode(',', $this->string('filter.type')->toString()))))) : [];

        return array_values(array_filter($given, static fn (string $type): bool => in_array($type, CalendarEvent::TYPES, true)));
    }

    public function jobId(): ?int
    {
        return $this->filled('filter.job_id') ? $this->integer('filter.job_id') : null;
    }

    public function mine(): bool
    {
        return $this->boolean('filter.mine');
    }

    /** An unknown type is a validation error, not silently dropped. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('filter.type')) {
                return;
            }

            foreach (explode(',', $this->string('filter.type')->toString()) as $type) {
                if (! in_array(trim($type), CalendarEvent::TYPES, true)) {
                    $validator->errors()->add('filter.type', 'The type '.trim($type).' is not one of '.implode(', ', CalendarEvent::TYPES).'.');
                }
            }
        });
    }
}
