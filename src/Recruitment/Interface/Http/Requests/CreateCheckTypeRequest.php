<?php

namespace Src\Recruitment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Src\Recruitment\Domain\CheckKind;
use Src\Recruitment\Domain\CheckType;

/** The body of a new check type: `name`, `kind`, optional `instructions` and `requires_consent` (true by default; always true for medical, background and police record). */
final class CreateCheckTypeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.(CheckType::NAME_MAX * 2)],
            'kind' => ['required', 'string', Rule::enum(CheckKind::class)],
            'instructions' => ['nullable', 'string', 'max:'.(CheckType::INSTRUCTIONS_MAX * 2)],
            'requires_consent' => ['sometimes', 'boolean'],
        ];
    }

    public function typeName(): string
    {
        return $this->string('name')->toString();
    }

    public function kind(): CheckKind
    {
        return CheckKind::from($this->string('kind')->toString());
    }

    public function instructions(): ?string
    {
        return $this->filled('instructions') ? $this->string('instructions')->toString() : null;
    }

    public function requiresConsent(): bool
    {
        return $this->boolean('requires_consent', true);
    }
}
