<?php

namespace Src\Recruitment\Interface\Http\Requests;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** The selection and the one check requested on all of it: `check_type_id` and the optional `scheduled_at`, `place`, `vendor` and `note_to_candidate` of the single request. */
final class BulkRequestChecksRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...BulkSelection::rules(),
            'check_type_id' => ['required', 'integer', 'min:1'],
            ...UpdateCheckRequest::fields(),
        ];
    }

    /** @return list<int> */
    public function applicationIds(): array
    {
        return BulkSelection::ids($this->validated('application_ids'));
    }

    public function checkTypeId(): int
    {
        return $this->integer('check_type_id');
    }

    public function scheduledAt(): ?DateTimeImmutable
    {
        return $this->filled('scheduled_at') ? new DateTimeImmutable($this->string('scheduled_at')->toString()) : null;
    }

    public function place(): ?string
    {
        return $this->filled('place') ? $this->string('place')->toString() : null;
    }

    public function vendor(): ?string
    {
        return $this->filled('vendor') ? $this->string('vendor')->toString() : null;
    }

    public function noteToCandidate(): ?string
    {
        return $this->filled('note_to_candidate') ? $this->string('note_to_candidate')->toString() : null;
    }
}
