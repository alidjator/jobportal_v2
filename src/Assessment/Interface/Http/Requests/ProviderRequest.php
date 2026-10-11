<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The body of registering and of renaming a provider. */
final class ProviderRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'legal_name' => ['required', 'string', 'min:2', 'max:200'],
        ];
    }

    public function name(): string
    {
        return $this->string('name')->toString();
    }

    public function legalName(): string
    {
        return $this->string('legal_name')->toString();
    }
}
