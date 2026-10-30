<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Src\Recruitment\Domain\CheckType;

/** The body that changes a check type: `name`, `instructions` and `requires_consent`. The kind never changes. */
final class UpdateCheckTypeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.(CheckType::NAME_MAX * 2)],
            'instructions' => ['nullable', 'string', 'max:'.(CheckType::INSTRUCTIONS_MAX * 2)],
            'requires_consent' => ['required', 'boolean'],
        ];
    }

    public function typeName(): string
    {
        return $this->string('name')->toString();
    }

    public function instructions(): ?string
    {
        return $this->filled('instructions') ? $this->string('instructions')->toString() : null;
    }

    public function requiresConsent(): bool
    {
        return $this->boolean('requires_consent');
    }
}
