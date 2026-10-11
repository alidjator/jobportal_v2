<?php

namespace Src\Assessment\Interface\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** The body of registering and of changing a provider: its short code (2 to 10 letters or digits), name and legal name. */
final class ProviderRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^[A-Z0-9]{2,10}$/'],
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'legal_name' => ['required', 'string', 'min:2', 'max:200'],
        ];
    }

    /** The code is typed in capitals or lowercase and kept in capitals. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    public function code(): string
    {
        return $this->string('code')->toString();
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
