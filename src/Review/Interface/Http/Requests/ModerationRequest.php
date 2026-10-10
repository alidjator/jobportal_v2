<?php

namespace Src\Review\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Review\Domain\ModerationReason;

/** The reason code and an optional note an admin gives when rejecting or taking down a review or a reply. */
final class ModerationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::enum(ModerationReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function reason(): ModerationReason
    {
        return ModerationReason::from($this->string('reason')->toString());
    }

    public function note(): ?string
    {
        return $this->filled('note') ? trim($this->string('note')->toString()) : null;
    }
}
