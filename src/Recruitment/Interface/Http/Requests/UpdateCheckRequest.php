<?php

namespace Src\Recruitment\Interface\Http\Requests;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Src\Recruitment\Domain\ApplicationCheck;

/** The body that changes an open check: `scheduled_at`, `place`, `vendor` and `note_to_candidate`, each optional and sent whole (a missing one clears it). */
final class UpdateCheckRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public static function fields(): array
    {
        return [
            'scheduled_at' => ['nullable', 'date'],
            'place' => ['nullable', 'string', 'max:'.(ApplicationCheck::PLACE_MAX * 2)],
            'vendor' => ['nullable', 'string', 'max:'.(ApplicationCheck::VENDOR_MAX * 2)],
            'note_to_candidate' => ['nullable', 'string', 'max:'.(ApplicationCheck::NOTE_MAX * 2)],
        ];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return self::fields();
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
