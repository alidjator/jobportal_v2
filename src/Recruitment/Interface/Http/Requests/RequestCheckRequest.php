<?php

namespace Src\Recruitment\Interface\Http\Requests;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** The body that requests a check: `check_type_id` and optional `scheduled_at`, `place`, `vendor`, `note_to_candidate`. */
final class RequestCheckRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'check_type_id' => ['required', 'integer', 'min:1'],
            ...UpdateCheckRequest::fields(),
        ];
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
